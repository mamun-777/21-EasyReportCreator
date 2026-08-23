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

- Open a Plant 3D project folder (no AutoCAD licence needed for listing)
- Eight list types with saved templates / company standards
- Excel export with logo placeholder, title block and revision table
- Excel import preview; write-back goes to `ProcessPower.demo-writeback.dcf` only (not in Version 1 scope)

## Notes

- `ProcessPower.dcf` is opened read-only.
- Do not upload this folder or `samples/` to STRATO — see `docs/Architecture.md`.
