from __future__ import annotations

import re
import sqlite3
from typing import Any

from .dcf import table_exists

_REVISION_DATE = re.compile(r"^Projectrevisie_Revisiedatum\s+(.+)$")
_REVISION_NOTE = re.compile(r"^Projectrevisie_Gewijzigd\s+(.+)$")

_STANDARD_KEYS = {
    "Project_Name",
    "Project_Description",
    "Project_Number",
    "Project_Standard",
    "Version",
    "ToolPaletteGroupName",
    "ToolPaletteGroupNameForPiping",
}


def _clean(value: Any) -> str:
    if value is None:
        return ""
    if isinstance(value, bytes):
        return ""
    return str(value).strip()


def _human_label(key: str) -> str:
    if key.startswith("S88_"):
        return key[4:].replace("_", " ")
    if key.startswith("Project_"):
        return key[8:].replace("_", " ")
    return key.replace("_", " ")


def _field_category(key: str) -> str:
    if key.startswith("Projectrevisie_"):
        return "revision_meta"
    if key.startswith("S88_"):
        return "custom"
    if key in _STANDARD_KEYS:
        return "standard"
    return "custom"


def _project_row(con: sqlite3.Connection) -> dict[str, Any]:
    if not table_exists(con, "PnPProject"):
        return {}
    row = con.execute("SELECT * FROM PnPProject LIMIT 1").fetchone()
    if not row:
        return {}
    return {k: _clean(v) for k, v in dict(row).items()}


def header_catalogue(con: sqlite3.Connection) -> list[dict[str, Any]]:
    row = _project_row(con)
    if not row:
        return []
    items: list[dict[str, Any]] = []
    for key in row:
        if key == "PnPID":
            continue
        category = _field_category(key)
        if category == "revision_meta":
            continue
        items.append(
            {
                "key": key,
                "label": _human_label(key),
                "category": category,
                "value": row.get(key, ""),
            }
        )
    order = {"standard": 0, "custom": 1}
    items.sort(key=lambda item: (order.get(item["category"], 9), item["label"].lower()))
    return items


def revision_rows_from_project(con: sqlite3.Connection) -> list[dict[str, str]]:
    row = _project_row(con)
    if not row:
        return []
    by_rev: dict[str, dict[str, str]] = {}
    for key, value in row.items():
        m = _REVISION_DATE.match(key)
        if m:
            by_rev.setdefault(m.group(1), {})["date"] = value
            continue
        m = _REVISION_NOTE.match(key)
        if m:
            by_rev.setdefault(m.group(1), {})["desc"] = value

    def sort_key(rev: str) -> tuple:
        if rev.isdigit():
            return (0, int(rev))
        return (1, rev)

    rows: list[dict[str, str]] = []
    for rev in sorted(by_rev, key=sort_key):
        item = by_rev[rev]
        if not item.get("date") and not item.get("desc"):
            continue
        rows.append(
            {
                "rev": rev,
                "date": item.get("date", ""),
                "desc": item.get("desc", ""),
                "drawn": "",
                "checked": "",
                "approved": "",
            }
        )
    return rows


def resolve_header_block(
    template: dict[str, Any],
    project_row: dict[str, Any],
) -> dict[str, Any]:
    header = dict(template.get("header") or {})
    selected = header.get("fields") or _default_header_fields()
    fields: list[dict[str, str]] = []
    for item in selected:
        key = item.get("key") or ""
        if not key:
            continue
        label = item.get("label") or _human_label(key)
        fields.append({"key": key, "label": label, "value": project_row.get(key, "")})
    header["fields"] = fields
    if not header.get("title"):
        header["title"] = template.get("name") or "Plant 3D List"
    if not header.get("document_number") and project_row.get("Project_Number"):
        header["document_number"] = f"{project_row['Project_Number']}-{template.get('id', 'LST')}"
    return header


def _default_header_fields() -> list[dict[str, str]]:
    return [
        {"key": "Project_Name", "label": "Project"},
        {"key": "Project_Description", "label": "Description"},
        {"key": "Project_Number", "label": "Project number"},
        {"key": "S88_Projectstatus", "label": "Status"},
        {"key": "S88_Locatie", "label": "Location"},
    ]


def merge_template_header(template: dict[str, Any], project_details: dict[str, Any]) -> dict[str, Any]:
    merged = dict(template)
    project_row = project_details.get("values") or {}
    merged["header"] = resolve_header_block(template, project_row)
    revisions = template.get("revision_table") or []
    if not revisions and project_details.get("revisions"):
        merged["revision_table"] = project_details["revisions"]
    return merged
