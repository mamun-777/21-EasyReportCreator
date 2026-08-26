"""Class tree + property catalogue from Plant 3D PnP metadata (WP3).

Reads PnPTables / PnPProperties / PnPColumnAttributes / PnPTableAttributes
from ProcessPower.dcf — the same hierarchy shown in Project Setup →
P&ID Class Definitions → Engineering Items.
"""

from __future__ import annotations

import sqlite3
from typing import Any

from .dcf import table_exists

# Report list source → root class in the PnP hierarchy (picker will use this Thu 27)
SOURCE_ROOT_CLASS: dict[str, str] = {
    "drawings": "PnPDrawings",
    "equipment": "Equipment",
    "valves": "HandValves",
    "control_valves": "Gestuurdeafsluiters",
    "instruments": "Instrumentation",
    "lines": "PipeLines",
    "line_summary": "PipeLineGroup",
    "components": "EngineeringItems",
}

_DEFAULT_ROOT = "EngineeringItems"

# Prefer English labels for well-known Engineering Items properties (R9 screenshot)
_ENGLISH_LABELS: dict[str, str] = {
    "ClassName": "Class Name",
    "Status": "Status",
    "Tag": "Tag",
    "PnPID": "PnPID",
    "Size": "Size",
    "Spec": "Spec",
    "Material": "Material",
    "Mat": "Material",
    "Omschrijving": "Description",
    "ProcMed": "Medium",
    "ProcAanslDiam": "Process connection diameter",
    "OudeTagNummer": "Old tag",
    "Tagopmerking": "Tag remark",
    "Spanning": "Voltage (V)",
    "Stroom": "Nominal current (A)",
    "EVerm": "Electrical power",
    "Eindcontacten": "End contacts",
    "NONC": "NO / NC",
    "Meetsignaal": "Measuring signal",
    "Alarmtypehoog": "Alarm type high",
    "Normally": "Normally",
    "EndConnections": "End connections",
    "Manufacturer": "Manufacturer",
    "Fabrikant": "Manufacturer",
    "Type": "Type",
    "Remarks": "Remarks",
    "Opm": "Remarks",
}


def _truthy(value: Any) -> bool:
    if value is None:
        return False
    return str(value).strip().lower() in {"1", "true", "yes", "y"}


def _clean(value: Any) -> str:
    if value is None:
        return ""
    if isinstance(value, bytes):
        return ""
    return str(value).strip()


def _human_label(key: str) -> str:
    if key in _ENGLISH_LABELS:
        return _ENGLISH_LABELS[key]
    # Split CamelCase / underscores lightly
    spaced = key.replace("_", " ")
    out: list[str] = []
    for i, ch in enumerate(spaced):
        if i and ch.isupper() and spaced[i - 1].islower():
            out.append(" ")
        out.append(ch)
    return "".join(out)


def _require_meta(con: sqlite3.Connection) -> None:
    for name in ("PnPTables", "PnPProperties"):
        if not table_exists(con, name):
            raise RuntimeError(f"DCF is missing {name}; cannot build class catalogue.")


def _table_display_names(con: sqlite3.Connection) -> dict[str, str]:
    if not table_exists(con, "PnPTableAttributes"):
        return {}
    rows = con.execute(
        """
        SELECT TableName, AttributeValue
        FROM PnPTableAttributes
        WHERE AttributeName = 'DisplayName'
        """
    ).fetchall()
    return {r[0]: _clean(r[1]) for r in rows if r[0]}


def _load_tables(con: sqlite3.Connection) -> list[dict[str, Any]]:
    return [
        {
            "name": r[0],
            "base": r[1] or "",
            "abstract": _truthy(r[2]),
            "physical_name": _clean(r[3]),
        }
        for r in con.execute(
            "SELECT TableName, BaseTable, Abstract, PhysicalName FROM PnPTables"
        )
    ]


def class_ancestors(con: sqlite3.Connection, class_name: str) -> list[str]:
    """Walk BaseTable links from class toward PnPBase (inclusive of class)."""
    _require_meta(con)
    by_name = {t["name"]: t for t in _load_tables(con)}
    chain: list[str] = []
    cur = class_name
    seen: set[str] = set()
    while cur and cur not in seen:
        if cur not in by_name:
            break
        seen.add(cur)
        chain.append(cur)
        cur = by_name[cur]["base"]
    return chain


def class_tree(
    con: sqlite3.Connection,
    root: str = _DEFAULT_ROOT,
    *,
    max_depth: int | None = None,
) -> dict[str, Any]:
    """
    Nested Engineering Items (or other root) tree from PnPTables.

    Each node: id, name, display_name, abstract, children[].
    """
    _require_meta(con)
    tables = _load_tables(con)
    by_name = {t["name"]: t for t in tables}
    if root not in by_name:
        raise KeyError(f"Class '{root}' not found in PnPTables")

    children_map: dict[str, list[str]] = {}
    for t in tables:
        if t["base"]:
            children_map.setdefault(t["base"], []).append(t["name"])
    for names in children_map.values():
        names.sort(key=str.lower)

    displays = _table_display_names(con)

    def build(name: str, depth: int) -> dict[str, Any]:
        meta = by_name[name]
        node: dict[str, Any] = {
            "id": name,
            "name": name,
            "display_name": displays.get(name) or _human_label(name),
            "abstract": meta["abstract"],
            "children": [],
        }
        if max_depth is not None and depth >= max_depth:
            return node
        for child in children_map.get(name, []):
            node["children"].append(build(child, depth + 1))
        return node

    tree = build(root, 0)
    tree["root"] = root
    tree["node_count"] = _count_nodes(tree)
    return tree


def _count_nodes(node: dict[str, Any]) -> int:
    return 1 + sum(_count_nodes(c) for c in node.get("children") or [])


def flatten_class_tree(node: dict[str, Any], *, depth: int = 0) -> list[dict[str, Any]]:
    """Flat list for simple UI / debugging (excludes nested children keys)."""
    item = {
        "id": node["id"],
        "name": node["name"],
        "display_name": node["display_name"],
        "abstract": node.get("abstract", False),
        "depth": depth,
        "child_count": len(node.get("children") or []),
    }
    out = [item]
    for child in node.get("children") or []:
        out.extend(flatten_class_tree(child, depth=depth + 1))
    return out


def _column_attributes(
    con: sqlite3.Connection, table_name: str
) -> dict[str, dict[str, str]]:
    if not table_exists(con, "PnPColumnAttributes"):
        return {}
    attrs: dict[str, dict[str, str]] = {}
    for row in con.execute(
        """
        SELECT ColumnName, AttributeName, AttributeValue
        FROM PnPColumnAttributes
        WHERE TableName = ?
        """,
        (table_name,),
    ):
        col, attr, val = row[0], str(row[1] or "").upper(), _clean(row[2])
        attrs.setdefault(col, {})[attr] = val
    return attrs


def _properties_defined_on(con: sqlite3.Connection, table_name: str) -> list[dict[str, Any]]:
    attrs = _column_attributes(con, table_name)
    items: list[dict[str, Any]] = []
    for row in con.execute(
        """
        SELECT PropertyName, PropertyType, IsSystem, IsExpression, Expression, Length, IsUnique
        FROM PnPProperties
        WHERE TableName = ?
        ORDER BY PropertyName
        """,
        (table_name,),
    ):
        key = row[0]
        meta = attrs.get(key, {})
        display = meta.get("DISPLAYNAME") or ""
        items.append(
            {
                "key": key,
                "label": display or _human_label(key),
                "type": _clean(row[1]) or meta.get("TYPE", ""),
                "table_defined_on": table_name,
                "is_system": _truthy(row[2]),
                "is_expression": _truthy(row[3]),
                "expression": _clean(row[4]),
                "length": row[5],
                "is_unique": _truthy(row[6]),
                "is_readonly": _truthy(meta.get("ISREADONLY")),
                "is_hidden": _truthy(meta.get("ISHIDDEN")),
                "description": meta.get("DESCRIPTION", ""),
                "picklist": meta.get("PICKLISTNAME", ""),
            }
        )
    return items


def properties_for_class(
    con: sqlite3.Connection,
    class_name: str,
    *,
    include_inherited: bool = True,
    include_system: bool = False,
    include_hidden: bool = True,
) -> list[dict[str, Any]]:
    """
    Property catalogue for one class.

    With include_inherited=True, walks ancestors and merges properties
    (child definition wins on duplicate PropertyName).
    """
    _require_meta(con)
    by_name = {t["name"]: t for t in _load_tables(con)}
    if class_name not in by_name:
        raise KeyError(f"Class '{class_name}' not found in PnPTables")

    chain = class_ancestors(con, class_name) if include_inherited else [class_name]
    # Walk from root ancestor → leaf so child overrides parent
    merged: dict[str, dict[str, Any]] = {}
    for table in reversed(chain):
        if table == "PnPBase" and not include_system:
            # Still collect; filter below — keep walk for completeness
            pass
        for prop in _properties_defined_on(con, table):
            merged[prop["key"]] = prop

    items = list(merged.values())
    if not include_system:
        items = [p for p in items if not p["is_system"]]
    if not include_hidden:
        items = [p for p in items if not p["is_hidden"]]

    items.sort(
        key=lambda p: (
            0 if p["table_defined_on"] == class_name else 1,
            p["label"].lower(),
            p["key"].lower(),
        )
    )
    return items


def class_display_name(con: sqlite3.Connection, class_name: str) -> str:
    return _table_display_names(con).get(class_name) or _human_label(class_name)


def property_catalogue_for_source(
    con: sqlite3.Connection,
    source: str,
    **kwargs: Any,
) -> dict[str, Any]:
    """Resolve a report list source to its root class + property list."""
    class_name = SOURCE_ROOT_CLASS.get(source)
    if not class_name:
        raise KeyError(f"Unknown report source: {source}")
    props = properties_for_class(con, class_name, **kwargs)
    return {
        "source": source,
        "class_name": class_name,
        "display_name": class_display_name(con, class_name),
        "ancestors": class_ancestors(con, class_name),
        "properties": props,
        "count": len(props),
    }


def source_class_map() -> list[dict[str, str]]:
    return [
        {"source": source, "class_name": class_name}
        for source, class_name in SOURCE_ROOT_CLASS.items()
    ]
