# Project structure

```
21-EasyReportCreator/
├── README.md
├── PROJECT-STRUCTURE.md
│
├── app/                          Report application (Python FastAPI)
│   ├── app.py                    http://127.0.0.1:8765
│   ├── plant3d/                  DCF, queries, Excel, templates
│   ├── report_templates/         Company-standard JSON
│   ├── static/                   Web UI (light theme — shared/tokens.css)
│   ├── data/                     Logo, state
│   └── run.bat
│
├── website/                      PHP 8 public site → STRATO / easyreportcreator.com
│   ├── inc/                      config, header, footer
│   ├── assets/                   Light stylesheet + JS
│   └── *.php
│
├── samples/                      Plant 3D acceptance fixtures (not deployed)
│   └── MN-P-RHN-PID-0001/
│
├── reference/                    Correspondence and demo video (not deployed)
│   ├── correspondence/
│   └── media/
│
├── shared/
│   └── tokens.css                Light design tokens
│
├── docs/                         Requirements, estimate, architecture, schedule
│
└── scripts/
    └── start-website.bat         Local PHP preview
```

## What runs where

| Path | Runtime | Deploy |
|---|---|---|
| `website/` | PHP 8 | STRATO — easyreportcreator.com |
| `app/` | Python 3 + FastAPI | Local / intranet PC with access to the Plant 3D folder |
| `samples/`, `reference/` | — | Keep in repo only; do not upload to webspace |

## Preview

```bat
app\run.bat
scripts\start-website.bat
```
