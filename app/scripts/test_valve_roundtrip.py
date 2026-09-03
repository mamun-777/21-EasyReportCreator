"""WP4 valve list template round-trip test (Mon 31)."""
from __future__ import annotations

import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))

from plant3d.dcf import connect, load_project
from plant3d.excel import export_workbook
from plant3d.project import merge_template_header
from plant3d.queries import run_source
from plant3d.templates import apply_template, load_template, save_template, standard_template_id

SAMPLE = ROOT.parent / "samples" / "MN-P-RHN-PID-0001"


def main() -> None:
    info, dcf_path = load_project(SAMPLE)
    base = load_template("valve_list")
    std_id = standard_template_id("valve_list")
    std = dict(base)
    std["id"] = std_id
    std["name"] = "Valve List (company standard)"
    std["header"]["revision"] = "B"
    std["revision_table"] = [
        {"rev": "B", "date": "2026-08-31", "desc": "Round-trip test", "drawn": "ERC", "checked": "", "approved": ""}
    ]
    for col in std["columns"]:
        col["visible"] = col["key"] in {"Tag", "ObjectType", "Omschrijving", "LineNumber"}
    save_template(std, overwrite=True)

    reloaded = load_template(std_id)
    assert reloaded["header"]["revision"] == "B"
    assert sum(1 for c in reloaded["columns"] if c.get("visible", True)) == 4

    with connect(dcf_path) as con:
        details = {"values": {}, "revisions": reloaded.get("revision_table", [])}
        merged = merge_template_header(reloaded, details)
        raw = run_source(con, merged["source"])
    rows = apply_template(raw, merged)
    assert len(rows) == info.counts["hand_valves"], (len(rows), info.counts["hand_valves"])

    workbook = export_workbook(rows, merged, info.__dict__, None)
    assert len(workbook) > 5000
    print("OK valve round-trip:", len(rows), "rows,", len(workbook), "bytes xlsx")

if __name__ == "__main__":
    main()
