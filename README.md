# EasyReportCreator

Friendlier replacement for AutoCAD Plant 3D Report Creator. Reads lists from the Plant 3D project database (`ProcessPower.dcf`) and issues valve, equipment, line, instrument, drawing and component lists to Excel.

Version 1 is a **local/intranet web app** plus a **light public site** at [easyreportcreator.com](https://easyreportcreator.com), hosted on STRATO.

| Item | Location |
|---|---|
| Architecture (PHP site + Python app) | `docs/Architecture.md` |
| Weekly / daily 100 h schedule | `docs/Delivery-Schedule.md` |
| Upwork AM/PM time log | `docs/Upwork-Time-Log.md` |
| Folder map | `PROJECT-STRUCTURE.md` |
| Customer requirements | `docs/EasyReportCreator-Requirements.md` |
| Commercial estimate | `docs/EasyReportCreator-Estimate.md` |
| Report app | `app/` — `app/run.bat` then http://127.0.0.1:8765 |
| Public site (PHP, light) | `website/` — `scripts/start-website.bat` |
| Sample Plant 3D project | `samples/MN-P-RHN-PID-0001/` |
| Correspondence & demo video | `reference/` |

## Preview

```bat
app\run.bat
scripts\start-website.bat
```

The STRATO site is native PHP 8. The report engine stays Python because it must run next to the Plant 3D folder. Same split as [propertiesmanager.nl](https://www.propertiesmanager.nl/), with a light theme only.
