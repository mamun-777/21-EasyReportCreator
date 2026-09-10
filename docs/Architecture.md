# EasyReportCreator — Architecture

| Field | Value |
|---|---|
| Status | Locked for Version 1 (90–100 h) — **updated Tue 1 Sep 2026** |
| Domain | **https://easyreportcreator.com** |
| Hosting | STRATO (www.strato.nl) |
| Theme | **Light only** (no night / dark mode) |
| Visual reference | [propertiesmanager.nl](https://www.propertiesmanager.nl/) layout and type; light palette |
| **V1 deployment** | **Web app on STRATO** — user uploads `ProcessPower.dcf` |

This document records the production shape of the product.

---

## 1. What we are shipping (V1)

| Piece | Who uses it | Where it runs | Why |
|---|---|---|---|
| **Public site** | Prospects, Jan, ICT | STRATO at easyreportcreator.com | Product presence |
| **Report application** | Engineers | **Same STRATO site** (`/report/`) | Upload `.dcf` → lists → Excel in the browser |

Plant 3D does **not** need to be running. The `.dcf` file is SQLite and is usually only a few MB (Rhenen sample ~3.3 MB).

**Later option (not V1):** desktop / intranet app with **no upload** for companies that must keep project data fully on-site (may require Windows code signing).

---

## 2. Decision: PHP hosted app on STRATO

| Option | Verdict |
|---|---|
| Local Python only + PHP marketing site | Superseded for V1 after client decision (Tue 1 Sep) |
| Desktop installer + code signing | Deferred — later package if required |
| **PHP report engine on STRATO + DCF upload** | **Chosen for V1** |

STRATO shared hosting runs PHP 8 (`pdo_sqlite`, `zip`, FastCGI). The report engine under `website/inc/plant3d/` mirrors the proven Python SQL/templates/Excel behaviour.

Python under `app/` is retained for **local development and regression scripts** (`app/scripts/`).

---

## 3. Repository layout

```
21-EasyReportCreator/
├── website/                   ★ Deploy this folder to STRATO
│   ├── *.php                  Marketing pages
│   ├── report/                Report UI + api.php
│   ├── inc/plant3d/           DCF, Queries, Templates, Excel, Auth, Catalog (FB-001)
│   ├── report_templates/      Default list JSON (not the full property universe)
│   └── data/uploads/          Session DCF storage (writable)
├── app/                       Python reference + tests (not deployed to STRATO)
│   └── plant3d/catalog.py     Reference property discovery (port → Catalog.php)
├── samples/                   Local QA fixtures (gitignored .dcf)
│   ├── MN-P-RHN-PID-0001/     Vitens — regression counts only
│   └── _incoming/             Client-emailed projects before naming
├── shared/                    Design tokens
└── docs/
    ├── qa/                    ★ Client feedback log + FB tickets (UAT)
    ├── STRATO-Deploy.md
    ├── User-Guide.md
    └── …
```

**Multi-project rule (FB-001):** Vitens/`MN-P-RHN-PID-0001` validates list counts. Column catalogues must come from the **uploaded** DCF Engineering Items metadata (standard + user-defined), not from Vitens-only template keys.

---

## 4. Report flow (V1)

1. User opens `/report/`
2. Uploads `ProcessPower.dcf` (any Plant 3D project)
3. Chooses a list template (valve, equipment, line, …)
4. Selects columns from the **live property catalogue** for that project’s classes (R9 / FB-001)
5. Previews rows; exports English Excel

Write-back to the live DCF stays out of this package.

**Gap until FB-001 ships:** PHP still uses fixed template/SQL columns; other projects may error or show unknown properties. See `docs/qa/FB-001-engineering-items-properties.md`.

---

## 5. Public site

Home, Product, Pricing, Download, Contact, Terms — light theme. Download page points to `/report/`.

Deploy notes: **`docs/STRATO-Deploy.md`**.

---

## 6. Acceptance (V1)

1. Upload Rhenen sample `.dcf` on STRATO; counts match Project Details / tables.
2. Valve, equipment, and line lists preview correctly.
3. Excel export includes title metadata and English headers.
4. HTTPS works on easyreportcreator.com.
5. Desktop / no-upload noted as future option only.
6. **FB-001 / UAT-2:** second sample DCF uploads without errors; user can select all Engineering Items properties for a list (standard + user-defined). Client feedback: `docs/qa/`.
