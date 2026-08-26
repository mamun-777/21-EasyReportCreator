from __future__ import annotations

import json
from pathlib import Path
from typing import Any

from fastapi import FastAPI, File, Form, HTTPException, UploadFile
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
from plant3d.dcf import connect, find_project_dir, load_project
from plant3d.excel import apply_changes_to_copy, export_workbook, read_imported_excel
from plant3d.project import (
    catalogue_groups,
    header_catalogue,
    merge_template_header,
    revision_rows_from_project,
)
from plant3d.queries import EDITABLE_FIELDS, FIELD_TO_COLUMN, run_source
from plant3d.templates import apply_template, list_templates, load_template, save_template

ROOT = Path(__file__).resolve().parent
REPO_ROOT = ROOT.parent
STATIC = ROOT / "static"
DATA = ROOT / "data"
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
    path = _resolve_project_path(_state().get("project_path") or SAMPLE_PROJECT)
    if not path.exists():
        raise HTTPException(400, "Plant 3D project folder not found. Open the sample project first.")
    return path


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
    state = _state()
    state["project_path"] = str(find_project_dir(path))
    _save_state(state)
    return {"ok": True, "project": info.__dict__}


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


@app.get("/api/templates")
def templates() -> list[dict[str, Any]]:
    return list_templates()


@app.get("/api/templates/{template_id}")
def get_template(template_id: str) -> dict[str, Any]:
    try:
        return load_template(template_id)
    except FileNotFoundError as exc:
        raise HTTPException(404, f"Template {template_id} not found") from exc


@app.post("/api/templates")
def post_template(payload: dict[str, Any]) -> dict[str, Any]:
    return save_template(payload)


@app.get("/api/report/{template_id}")
def report(template_id: str) -> dict[str, Any]:
    template = _merged_template(load_template(template_id))
    info, dcf_path = load_project(_project_path())
    with connect(dcf_path) as con:
        raw = run_source(con, template["source"])
    rows = apply_template(raw, template)
    return {
        "template": template,
        "project": info.__dict__,
        "row_count": len(rows),
        "raw_count": len(raw),
        "rows": rows,
    }


@app.get("/api/export/{template_id}")
def export_report(template_id: str) -> Response:
    template = _merged_template(load_template(template_id))
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
