from __future__ import annotations

import json
import re
import shutil
import uuid
from pathlib import Path
from typing import Any

from fastapi import FastAPI, File, Form, HTTPException, Query, UploadFile
from fastapi.responses import FileResponse, JSONResponse, Response
from fastapi.staticfiles import StaticFiles

from plant3d.catalog import (
    class_ancestors,
    class_display_name,
    class_tree,
    flatten_class_tree,
    properties_for_class,
    property_catalogue_for_source,
    source_class_map,
)
from plant3d.dcf import connect, find_project_dir, load_project, validate_dcf
from plant3d.excel import apply_changes_to_copy, export_workbook, read_imported_excel
from plant3d.project import (
    catalogue_groups,
    header_catalogue,
    merge_template_header,
    revision_rows_from_project,
)
from plant3d.queries import EDITABLE_FIELDS, FIELD_TO_COLUMN, run_source
from plant3d.templates import (
    apply_template,
    list_templates,
    load_template,
    resolve_template_id,
    save_template,
    seed_standard_templates,
    template_exists,
)

ROOT = Path(__file__).resolve().parent
REPO_ROOT = ROOT.parent
STATIC = ROOT / "static"
DATA = ROOT / "data"
UPLOADS_ROOT = DATA / "uploads"
SAMPLE_PROJECT = REPO_ROOT / "samples" / "MN-P-RHN-PID-0001"
STATE_FILE = DATA / "state.json"

SHARED = REPO_ROOT / "shared"

app = FastAPI(title="EasyReportCreator", version="0.1.0")
app.mount("/shared", StaticFiles(directory=SHARED), name="shared")
app.mount("/static", StaticFiles(directory=STATIC), name="static")


def _state() -> dict[str, Any]:
    DATA.mkdir(parents=True, exist_ok=True)
    if STATE_FILE.exists():
        return json.loads(STATE_FILE.read_text(encoding="utf-8"))
    return {"project_path": str(SAMPLE_PROJECT)}


def _save_state(state: dict[str, Any]) -> None:
    DATA.mkdir(parents=True, exist_ok=True)
    STATE_FILE.write_text(json.dumps(state, indent=2), encoding="utf-8")


def _resolve_project_path(raw: str | Path) -> Path:
    path = Path(raw)
    if not path.is_absolute():
        path = REPO_ROOT / path
    return path


def _project_path() -> Path:
    state = _state()
    path = _resolve_project_path(state.get("project_path") or SAMPLE_PROJECT)
    if not path.exists():
        raise HTTPException(400, "Plant 3D project folder not found. Open a .dcf file first.")
    if state.get("source") == "upload" and not any(path.glob("*.dcf")):
        raise HTTPException(400, "Uploaded project not found. Please upload the .dcf file again.")
    return path


def _sanitize_filename(name: str) -> str:
    stem = Path(name).stem
    safe = re.sub(r"[^\w.\-]+", "_", stem).strip("._")
    return safe or "ProcessPower"


def _new_upload_session_dir() -> Path:
    session_dir = UPLOADS_ROOT / uuid.uuid4().hex
    session_dir.mkdir(parents=True, exist_ok=True)
    return session_dir


def _prune_upload_sessions(keep: Path) -> None:
    """Remove older upload folders; ignore Windows file locks on in-use databases."""
    if not UPLOADS_ROOT.exists():
        return
    keep_resolved = keep.resolve()
    for child in UPLOADS_ROOT.iterdir():
        if not child.is_dir() or child.resolve() == keep_resolved:
            continue
        shutil.rmtree(child, ignore_errors=True)


def _project_payload(
    info: Any,
    *,
    source: str = "folder",
    uploaded_filename: str = "",
    include_drawings: bool = False,
) -> dict[str, Any]:
    payload = dict(info.__dict__)
    if not include_drawings:
        payload.pop("drawings", None)
    payload["source"] = source
    payload["uploaded_filename"] = uploaded_filename
    return payload


def _logo_path() -> Path | None:
    for name in ("logo.png", "logo.jpg", "logo.jpeg"):
        candidate = DATA / name
        if candidate.exists():
            return candidate
    return None


@app.get("/")
def index() -> FileResponse:
    return FileResponse(STATIC / "index.html")


@app.get("/api/health")
def health() -> dict[str, str]:
    return {"status": "ok", "product": "EasyReportCreator"}


@app.get("/api/sample-path")
def sample_path() -> JSONResponse:
    return JSONResponse({"path": str(SAMPLE_PROJECT), "exists": SAMPLE_PROJECT.exists()})


@app.post("/api/open-project")
def open_project(payload: dict[str, str]) -> dict[str, Any]:
    path = payload.get("path") or str(SAMPLE_PROJECT)
    try:
        info, _ = load_project(path)
    except FileNotFoundError as exc:
        raise HTTPException(400, str(exc)) from exc
    except ValueError as exc:
        raise HTTPException(400, str(exc)) from exc
    state = _state()
    state["project_path"] = str(find_project_dir(path))
    state["source"] = "folder"
    state.pop("uploaded_filename", None)
    _save_state(state)
    return {"ok": True, "project": _project_payload(info, source="folder")}


@app.post("/api/upload-project")
async def upload_project(file: UploadFile = File(...)) -> dict[str, Any]:
    """Upload a local ProcessPower.dcf for analysis (streamed; suitable for large files)."""
    original_name = file.filename or "ProcessPower.dcf"
    if not original_name.lower().endswith(".dcf"):
        raise HTTPException(400, "Please select a Plant 3D database file (.dcf).")

    session_dir = _new_upload_session_dir()
    # Store under a stable name so later API calls can resolve the upload folder.
    target = session_dir / "ProcessPower.dcf"
    try:
        with target.open("wb") as buffer:
            shutil.copyfileobj(file.file, buffer)
    except OSError as exc:
        shutil.rmtree(session_dir, ignore_errors=True)
        raise HTTPException(500, f"Could not save uploaded file: {exc}") from exc

    try:
        validate_dcf(target)
        info, _ = load_project(target, include_drawings=False)
    except (FileNotFoundError, ValueError) as exc:
        shutil.rmtree(session_dir, ignore_errors=True)
        raise HTTPException(400, str(exc)) from exc
    except Exception as exc:
        shutil.rmtree(session_dir, ignore_errors=True)
        raise HTTPException(400, f"Could not read Plant 3D database: {exc}") from exc

    state = _state()
    state["project_path"] = str(session_dir)
    state["source"] = "upload"
    state["uploaded_filename"] = original_name
    _save_state(state)
    _prune_upload_sessions(session_dir)
    return {
        "ok": True,
        "project": _project_payload(info, source="upload", uploaded_filename=original_name),
        "filename": original_name,
        "bytes": target.stat().st_size,
    }


@app.get("/api/project")
def project() -> dict[str, Any]:
    info, _ = load_project(_project_path())
    return info.__dict__


@app.get("/api/project/details")
def project_details() -> dict[str, Any]:
    """Project Details header catalogue: standard + custom (S88) fields from PnPProject."""
    _, dcf_path = load_project(_project_path())
    with connect(dcf_path) as con:
        catalogue = header_catalogue(con)
        revisions = revision_rows_from_project(con)
        values = {item["key"]: item["value"] for item in catalogue}
    return {
        "catalogue": catalogue,
        "groups": catalogue_groups(catalogue),
        "values": values,
        "revisions": revisions,
    }


@app.get("/api/project/header-catalogue")
def project_header_catalogue() -> dict[str, Any]:
    """Alias focused on Mon 24 WP2: flat catalogue + grouped categories."""
    return project_details()


@app.get("/api/project/class-tree")
def project_class_tree(root: str = "EngineeringItems", flat: bool = False) -> dict[str, Any]:
    """WP3: Engineering Items (or other) class hierarchy from PnPTables."""
    _, dcf_path = load_project(_project_path())
    with connect(dcf_path) as con:
        try:
            tree = class_tree(con, root=root)
        except KeyError as exc:
            raise HTTPException(404, str(exc)) from exc
        except RuntimeError as exc:
            raise HTTPException(400, str(exc)) from exc
    payload: dict[str, Any] = {
        "root": tree["root"],
        "node_count": tree["node_count"],
        "tree": tree,
        "sources": source_class_map(),
    }
    if flat:
        payload["classes"] = flatten_class_tree(tree)
    return payload


@app.get("/api/project/classes/{class_name}/properties")
def project_class_properties(
    class_name: str,
    include_inherited: bool = True,
    include_system: bool = False,
    include_hidden: bool = True,
) -> dict[str, Any]:
    """WP3: standard + user properties for one class (inherited via BaseTable)."""
    _, dcf_path = load_project(_project_path())
    with connect(dcf_path) as con:
        try:
            props = properties_for_class(
                con,
                class_name,
                include_inherited=include_inherited,
                include_system=include_system,
                include_hidden=include_hidden,
            )
            ancestors = class_ancestors(con, class_name)
            display = class_display_name(con, class_name)
        except KeyError as exc:
            raise HTTPException(404, str(exc)) from exc
        except RuntimeError as exc:
            raise HTTPException(400, str(exc)) from exc
    return {
        "class_name": class_name,
        "display_name": display,
        "ancestors": ancestors,
        "properties": props,
        "count": len(props),
    }


@app.get("/api/project/property-catalogue")
def project_property_catalogue(
    source: str | None = None,
    class_name: str | None = None,
    include_inherited: bool = True,
    include_system: bool = False,
    include_hidden: bool = True,
) -> dict[str, Any]:
    """WP3: property list for a report source or explicit class name."""
    if not source and not class_name:
        raise HTTPException(400, "Provide source= (e.g. valves) or class_name= (e.g. HandValves).")
    _, dcf_path = load_project(_project_path())
    with connect(dcf_path) as con:
        try:
            if source:
                return property_catalogue_for_source(
                    con,
                    source,
                    include_inherited=include_inherited,
                    include_system=include_system,
                    include_hidden=include_hidden,
                )
            assert class_name is not None
            props = properties_for_class(
                con,
                class_name,
                include_inherited=include_inherited,
                include_system=include_system,
                include_hidden=include_hidden,
            )
            return {
                "class_name": class_name,
                "display_name": class_display_name(con, class_name),
                "ancestors": class_ancestors(con, class_name),
                "properties": props,
                "count": len(props),
            }
        except KeyError as exc:
            raise HTTPException(404, str(exc)) from exc
        except RuntimeError as exc:
            raise HTTPException(400, str(exc)) from exc


def _merged_template(template: dict[str, Any]) -> dict[str, Any]:
    _, dcf_path = load_project(_project_path())
    with connect(dcf_path) as con:
        details = {
            "values": {item["key"]: item["value"] for item in header_catalogue(con)},
            "revisions": revision_rows_from_project(con),
        }
    return merge_template_header(template, details)


@app.on_event("startup")
def _seed_templates_on_startup() -> None:
    seed_standard_templates()


@app.get("/api/templates")
def templates() -> list[dict[str, Any]]:
    return list_templates()


@app.get("/api/templates/{template_id}")
def get_template(template_id: str, variant: str = Query("auto")) -> dict[str, Any]:
    try:
        if variant == "base":
            resolved = template_id.removesuffix("_standard")
        elif variant == "exact":
            resolved = template_id
        else:
            resolved = resolve_template_id(template_id)
        return load_template(resolved)
    except FileNotFoundError as exc:
        raise HTTPException(404, f"Template {template_id} not found") from exc


@app.post("/api/templates")
def post_template(payload: dict[str, Any]) -> dict[str, Any]:
    template = payload.get("template") if isinstance(payload.get("template"), dict) else payload
    overwrite = bool(payload.get("overwrite", True))
    template_id = template.get("id")
    if not template_id:
        raise HTTPException(400, "Template id is required")
    if template_exists(template_id) and not overwrite:
        raise HTTPException(
            409,
            f"Template '{template_id}' already exists. Choose overwrite to replace it.",
        )
    existed = template_exists(template_id)
    try:
        saved = save_template(template, overwrite=overwrite)
    except FileExistsError as exc:
        raise HTTPException(409, f"Template '{exc.args[0]}' already exists.") from exc
    return {"ok": True, "template": saved, "overwritten": existed, "id": saved["id"]}


@app.get("/api/report/{template_id}")
def report(template_id: str) -> dict[str, Any]:
    resolved = resolve_template_id(template_id)
    template = _merged_template(load_template(resolved))
    info, dcf_path = load_project(_project_path())
    with connect(dcf_path) as con:
        raw = run_source(con, template["source"])
    rows = apply_template(raw, template)
    return {
        "template": template,
        "template_id": template_id,
        "resolved_id": resolved,
        "project": info.__dict__,
        "row_count": len(rows),
        "raw_count": len(raw),
        "rows": rows,
    }


@app.get("/api/export/{template_id}")
def export_report(template_id: str) -> Response:
    resolved = resolve_template_id(template_id)
    template = _merged_template(load_template(resolved))
    info, dcf_path = load_project(_project_path())
    with connect(dcf_path) as con:
        raw = run_source(con, template["source"])
    rows = apply_template(raw, template)
    content = export_workbook(rows, template, info.__dict__, _logo_path())
    filename = f"{info.number or info.name}_{template_id}.xlsx"
    return Response(
        content=content,
        media_type="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        headers={"Content-Disposition": f'attachment; filename="{filename}"'},
    )


@app.get("/api/logo")
def get_logo() -> Response:
    path = _logo_path()
    if not path:
        raise HTTPException(404, "No logo uploaded")
    media = "image/png" if path.suffix.lower() == ".png" else "image/jpeg"
    return FileResponse(path, media_type=media, headers={"Cache-Control": "no-store"})


@app.post("/api/logo")
async def upload_logo(file: UploadFile = File(...)) -> JSONResponse:
    suffix = Path(file.filename or "logo.png").suffix.lower()
    if suffix not in {".png", ".jpg", ".jpeg"}:
        raise HTTPException(400, "Please upload a PNG or JPEG logo.")
    DATA.mkdir(parents=True, exist_ok=True)
    for old in DATA.glob("logo.*"):
        if old.suffix.lower() in {".png", ".jpg", ".jpeg"}:
            old.unlink(missing_ok=True)
    target = DATA / ("logo.png" if suffix == ".png" else "logo.jpg")
    target.write_bytes(await file.read())
    return JSONResponse({"ok": True, "url": "/api/logo"})


@app.post("/api/import/preview")
async def import_preview(file: UploadFile = File(...), template_id: str = Form("")) -> dict[str, Any]:
    content = await file.read()
    imported, meta = read_imported_excel(content)
    tid = template_id or meta.get("template_id") or ""
    if not tid:
        raise HTTPException(400, "Export from EasyReportCreator first, or select a template.")
    template = load_template(tid)
    info, dcf_path = load_project(_project_path())
    with connect(dcf_path) as con:
        current = apply_template(run_source(con, template["source"]), template)
    by_id = {str(row.get("PnPID")): row for row in current if row.get("PnPID") not in ("", None)}
    header_to_key = {c.get("header"): c["key"] for c in template.get("columns", [])}
    header_to_key["PnPID"] = "PnPID"

    changes = []
    unmatched = 0
    for item in imported:
        mapped = {}
        for header, value in item.items():
            key = header_to_key.get(header, header)
            mapped[key] = value
        pnpid = str(mapped.get("PnPID") or "")
        current_row = by_id.get(pnpid)
        if not current_row:
            unmatched += 1
            continue
        for field, new_value in mapped.items():
            if field in {"PnPID"} or field not in EDITABLE_FIELDS:
                continue
            old_value = current_row.get(field, "")
            new_s = "" if new_value is None else str(new_value).strip()
            old_s = "" if old_value is None else str(old_value).strip()
            if new_s != old_s:
                changes.append(
                    {
                        "pnpid": int(pnpid) if str(pnpid).isdigit() else pnpid,
                        "tag": current_row.get("Tag", ""),
                        "field": field,
                        "column": FIELD_TO_COLUMN[field],
                        "old": old_s,
                        "new": new_s,
                    }
                )
    return {
        "template_id": tid,
        "imported_rows": len(imported),
        "changes": changes,
        "unmatched": unmatched,
        "project": info.name,
    }


@app.post("/api/import/apply")
def import_apply(payload: dict[str, Any]) -> dict[str, Any]:
    changes = payload.get("changes") or []
    if not changes:
        return {"ok": True, "applied": 0, "path": None}
    info, dcf_path = load_project(_project_path())
    target = Path(info.project_dir) / "ProcessPower.demo-writeback.dcf"
    apply_changes_to_copy(dcf_path, target, changes)
    return {
        "ok": True,
        "applied": len(changes),
        "path": str(target),
        "note": "Original ProcessPower.dcf was not modified. Changes were written to a demo copy.",
    }


@app.get("/favicon.ico")
def favicon() -> Response:
    return Response(status_code=204)
