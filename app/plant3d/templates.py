from __future__ import annotations

import json
from copy import deepcopy
from pathlib import Path
from typing import Any

TEMPLATES_DIR = Path(__file__).resolve().parent.parent / "report_templates"


def _ensure_dir() -> Path:
    TEMPLATES_DIR.mkdir(parents=True, exist_ok=True)
    return TEMPLATES_DIR


def template_exists(template_id: str) -> bool:
    return (_ensure_dir() / f"{template_id}.json").exists()


def standard_template_id(base_id: str) -> str:
    base = base_id.removesuffix("_standard")
    return f"{base}_standard"


def list_templates() -> list[dict[str, Any]]:
    items = []
    for path in sorted(_ensure_dir().glob("*.json")):
        data = json.loads(path.read_text(encoding="utf-8"))
        template_id = data.get("id", path.stem)
        if template_id.endswith("_standard"):
            continue
        items.append(
            {
                "id": template_id,
                "name": data.get("name", path.stem),
                "name_nl": data.get("name_nl", data.get("name", path.stem)),
                "source": data.get("source"),
                "description": data.get("description", ""),
                "file": path.name,
                "has_standard": template_exists(standard_template_id(template_id)),
            }
        )
    return items


def load_template(template_id: str) -> dict[str, Any]:
    path = _ensure_dir() / f"{template_id}.json"
    if not path.exists():
        raise FileNotFoundError(template_id)
    return json.loads(path.read_text(encoding="utf-8"))


def resolve_template_id(template_id: str) -> str:
    """Prefer saved company standard when loading a base list type."""
    base = template_id.removesuffix("_standard")
    preferred = standard_template_id(base)
    if template_id == base and template_exists(preferred):
        return preferred
    return template_id


def save_template(data: dict[str, Any], *, overwrite: bool = True) -> dict[str, Any]:
    template_id = data.get("id")
    if not template_id:
        raise ValueError("Template id is required")
    safe = "".join(ch if ch.isalnum() or ch in "-_" else "_" for ch in template_id)
    path = _ensure_dir() / f"{safe}.json"
    if path.exists() and not overwrite:
        raise FileExistsError(safe)
    data = deepcopy(data)
    data["id"] = safe
    path.write_text(json.dumps(data, indent=2, ensure_ascii=False), encoding="utf-8")
    return data


def seed_standard_templates() -> list[str]:
    """Create missing {id}_standard.json files from base list templates."""
    created: list[str] = []
    for path in sorted(_ensure_dir().glob("*.json")):
        data = json.loads(path.read_text(encoding="utf-8"))
        base_id = data.get("id", path.stem)
        if base_id.endswith("_standard"):
            continue
        standard_id = standard_template_id(base_id)
        if template_exists(standard_id):
            continue
        copy = deepcopy(data)
        copy["id"] = standard_id
        name = copy.get("name", base_id)
        name_nl = copy.get("name_nl", name)
        if "(company standard)" not in name:
            copy["name"] = f"{name} (company standard)"
        if "(bedrijfsstandaard)" not in name_nl:
            copy["name_nl"] = f"{name_nl} (bedrijfsstandaard)"
        save_template(copy, overwrite=True)
        created.append(standard_id)
    return created


def apply_template(rows: list[dict[str, Any]], template: dict[str, Any]) -> list[dict[str, Any]]:
    columns = [c["key"] for c in template.get("columns", []) if c.get("visible", True)]
    sort_keys = template.get("sort") or []
    filters = template.get("filters") or {}

    filtered = []
    for row in rows:
        keep = True
        for field, expected in filters.items():
            if not expected:
                continue
            value = str(row.get(field, "") or "")
            if isinstance(expected, list):
                if value not in expected:
                    keep = False
                    break
            elif expected.lower() not in value.lower():
                keep = False
                break
        if keep:
            filtered.append(row)

    if sort_keys:
        def sort_tuple(row: dict[str, Any]) -> tuple:
            return tuple(str(row.get(k, "") or "") for k in sort_keys)

        filtered.sort(key=sort_tuple)

    projected = []
    for row in filtered:
        item = {key: row.get(key, "") for key in columns}
        if "PnPID" in row:
            item["PnPID"] = row["PnPID"]
        projected.append(item)
    return projected
