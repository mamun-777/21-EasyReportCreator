# EasyReportCreator — report app

Local web application that reads an AutoCAD Plant 3D project database (`ProcessPower.dcf`) and produces valve, equipment, line, instrument, drawing and component lists.

Built against the sample project in `../samples/MN-P-RHN-PID-0001` (Productiebedrijf Rhenen, Vitens PnId V6.1).

## Run

```bat
cd app
pip install -r requirements.txt
run.bat
```

Open http://127.0.0.1:8765 — the sample project path is filled in automatically.

## Current capabilities

- Open a Plant 3D project by **uploading `ProcessPower.dcf`** (local Python app) for development
- **Production (V1):** PHP report app under `../website/report/` on STRATO — same upload → lists → Excel flow; see `../docs/STRATO-Deploy.md`
- **Project Details header catalogue** (`GET /api/project/details`): standard Autodesk fields + user-defined categories (S88 on the Rhenen sample), grouped for the title-block picker
- **Class property catalogue** (WP3): Engineering Items tree (`GET /api/project/class-tree`) and per-class / per-list properties (`GET /api/project/classes/{name}/properties`, `GET /api/project/property-catalogue?source=valves`)
- **Header setup UI**: pick title-block fields, edit issue details, logo upload, revision table — Apply persists to the active template (preview + Excel export)
- Eight list types with saved templates / company standards (header.fields seeded)
- **Columns** dialog — show/hide columns per list; saved with company standard
- **Save template** — create/overwrite company standard, reset to factory default; auto-loads `_standard` when present
- **English Excel export** (WP5): logo, Project Details title block, revision history, English column headers, A3 landscape print layout
- Excel import preview; write-back goes to `ProcessPower.demo-writeback.dcf` only (not in Version 1 scope)

## WP6 acceptance scripts

```bat
cd app
python scripts/test_list_counts.py
python scripts/test_valve_roundtrip.py
python scripts/test_excel_export.py
```

Expected on MN-P-RHN-PID-0001: 344 hand valves, 80 equipment, 611 pipe lines.

## Notes

- `ProcessPower.dcf` is opened read-only.
- Do not upload this folder or `samples/` to STRATO — see `docs/Architecture.md`.
