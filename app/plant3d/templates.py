from __future__ import annotations

import json
from copy import deepcopy
from pathlib import Path
from typing import Any

TEMPLATES_DIR = Path(__file__).resolve().parent.parent / "report_templates"


def _ensure_dir() -> Path:
    TEMPLATES_DIR.mkdir(parents=True, exist_ok=True)
    return TEMPLATES_DIR


def list_templates() -> list[dict[str, Any]]:
    items = []
    for path in sorted(_ensure_dir().glob("*.json")):
        data = json.loads(path.read_text(encoding="utf-8"))
        items.append(
            {
                "id": data.get("id", path.stem),
                "name": data.get("name", path.stem),
                "name_nl": data.get("name_nl", data.get("name", path.stem)),
                "source": data.get("source"),
                "description": data.get("description", ""),
                "file": path.name,
            }
        )
    return items


def load_template(template_id: str) -> dict[str, Any]:
    path = _ensure_dir() / f"{template_id}.json"
    if not path.exists():
        raise FileNotFoundError(template_id)
    return json.loads(path.read_text(encoding="utf-8"))


def save_template(data: dict[str, Any]) -> dict[str, Any]:
    template_id = data.get("id")
    if not template_id:
        raise ValueError("Template id is required")
    safe = "".join(ch if ch.isalnum() or ch in "-_" else "_" for ch in template_id)
    data = deepcopy(data)
    data["id"] = safe
    path = _ensure_dir() / f"{safe}.json"
    path.write_text(json.dumps(data, indent=2, ensure_ascii=False), encoding="utf-8")
    return data


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
