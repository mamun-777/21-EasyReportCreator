"""WP6 — validate valve, equipment, and line list row counts vs sample DCF."""
from __future__ import annotations

import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))

from plant3d.dcf import connect, load_project
from plant3d.queries import run_source
from plant3d.templates import apply_template, load_template, resolve_template_id

SAMPLE = ROOT.parent / "samples" / "MN-P-RHN-PID-0001"

CHECKS = [
    ("valve_list", "valves", "hand_valves"),
    ("equipment_list", "equipment", "equipment"),
    ("line_list", "lines", "pipe_lines"),
]


def main() -> None:
    info, dcf_path = load_project(SAMPLE)
    print("Project:", info.name or info.number)
    print("Counts:", info.counts)

    with connect(dcf_path) as con:
        for template_id, source, count_key in CHECKS:
            resolved = resolve_template_id(template_id)
            template = load_template(resolved)
            raw = run_source(con, source)
            rows = apply_template(raw, template)
            expected = info.counts[count_key]
            assert len(raw) == expected, f"{source}: raw {len(raw)} != count {expected}"
            assert len(rows) == expected, (
                f"{resolved}: projected {len(rows)} != count {expected} "
                f"(visible cols may still keep all rows)"
            )
            print(f"OK {source}: {len(raw)} raw / {len(rows)} projected ({resolved})")

    print("OK WP6 list counts: valves, equipment, lines")


if __name__ == "__main__":
    main()
