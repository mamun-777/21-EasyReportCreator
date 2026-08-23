# EasyReportCreator — Architecture

| Field | Value |
|---|---|
| Status | Locked for Version 1 (90–100 h) |
| Date | 22 August 2026 |
| Domain | **https://easyreportcreator.com** |
| Hosting | STRATO (www.strato.nl) |
| Theme | **Light only** (no night / dark mode) |
| Visual reference | [propertiesmanager.nl](https://www.propertiesmanager.nl/) layout and type; light palette |

This document records the production shape of the product. It supersedes informal “Node then convert to PHP” thinking.

---

## 1. What we are actually shipping

EasyReportCreator is two cooperating pieces, the same split used for Inventor iProperties Manager:

| Piece | Who uses it | Where it runs | Why |
|---|---|---|---|
| **Public site** | Prospects, Jan, ICT | STRATO at easyreportcreator.com | Product presence: Home, Product, Pricing, Download, Contact |
| **Report application** | Engineers with a Plant 3D project | A PC or intranet machine that can **see the project folder** | Reads `ProcessPower.dcf` (SQLite) and `Project.xml`; issues Excel lists |

The 90–100 hour estimate is a **web application** that opens a Plant 3D project and exports lists. It is **not** a public multi-tenant cloud app. Public cloud + SSO is explicitly out of the paid package.

The customer’s source of truth (`ProcessPower.dcf`) lives next to AutoCAD Plant 3D, typically on a workstation or file share. STRATO shared hosting cannot see that folder. That is why the report engine does not run on STRATO.

---

## 2. Decision: PHP site + Python app (do not use Node)

**Build the STRATO site in PHP 8 from day one. Keep the report engine in Python. Do not start in Node and convert later.**

| Option | Verdict |
|---|---|
| Node / React SPA, convert to PHP at deploy | Rejected. Conversion burns 8–15 hours, doubles bugs, and STRATO does not run a Node process. |
| Rewrite the whole product in PHP on STRATO | Rejected for V1. The working demo is Python (DCF + Excel). A rewrite would consume the property-selection budget. STRATO still cannot open Jan’s local project folder. |
| PHP public site + Python local/intranet app | **Chosen.** Matches propertiesmanager.nl (static/PHP site on STRATO + local engineering tool). Reuses the demo. Fits 100 hours. |

STRATO shared hosting is a first-class PHP 8 environment (`pdo_sqlite`, `gd`, FastCGI). The propertiesmanager.nl site already deploys there as HTML/CSS/JS plus a small PHP license API. EasyReportCreator follows that pattern, with two improvements:

1. **PHP includes** for header/footer/config (one nav, not seven copied HTML files).
2. **Light theme only** — same page structure and fonts as propertiesmanager.nl, inverted tokens.

Python stays because it already does the hard work: read-only SQLite URI on `ProcessPower.dcf`, Plant 3D relationship CTEs, openpyxl title blocks. FastAPI is a local/intranet server (`app/run.bat`), not a STRATO process.

### Future path (not this package)

If a later package wants a **hosted** demo, `ProcessPower.dcf` for the sample project is ~3.3 MB — small enough to upload. PHP `PDO SQLite` could then read a copy on STRATO. That is a follow-on, not Version 1.

---

## 3. Target repository layout

```
21-EasyReportCreator/
├── app/                       Report application (Python FastAPI)
│   ├── plant3d/               DCF, queries, templates, Excel
│   ├── report_templates/      Company-standard JSON
│   ├── static/                Light UI (same tokens as the public site)
│   └── run.bat
├── website/                   PHP 8 public site → STRATO / easyreportcreator.com
│   ├── inc/                   config, header, footer
│   ├── assets/                styles.css, main.js
│   └── *.php
├── samples/                   Plant 3D acceptance fixtures
│   └── MN-P-RHN-PID-0001/
├── reference/                 Correspondence and demo video
├── shared/                    Design tokens used by website + app UI
└── docs/                      Requirements, estimate, this file, schedule
```

All application work lands in `app/` and `website/`. Sample data and reference material stay out of the deploy paths.

---

## 4. Report application (Python)

Layered so the 22-hour property-selection work does not tangle with Excel or HTTP.

```
app/
  plant3d/
    project.py      Project.xml + PnPProject + all custom property categories
    catalog.py      Class tree + standard/user properties per class (WP3)
    queries.py      List sources (valve, equipment, line, …)
    templates.py    Saved column sets, sort, header, revision rows
    excel.py        One-way English Excel (logo + title block + revision)
    dcf.py          SQLite connections (read-only by default)
  web/              Light HTML/CSS/JS — FastAPI static + JSON API
```

**Version 1 flow:** open project → choose list → pick columns from the class catalogue → preview → export Excel.

Write-back to the live DCF stays out of this package (optional Pro later). The demo’s import-to-copy path can remain hidden or labelled “not in V1”.

---

## 5. Public site (PHP, light, STRATO)

Same information architecture as propertiesmanager.nl:

| Page | Role |
|---|---|
| Home | Hero, why it exists, three features, proof panel, download CTA |
| Product | Feature grid, workflow, system requirements |
| Pricing | Version 1 delivery as quoted; volume / later licences by contact |
| Download | How to run the local/intranet app |
| Contact | TSPD / support |
| Terms | Delivery and support terms |

Theme rules (client request):

- `color-scheme: light` only
- Space Grotesk + Inter + JetBrains Mono (same as propertiesmanager.nl)
- Sticky top nav, hero + mock panel, feature cards, proof/diff, download card, footer
- No night toggle, no dark sidebar

Deploy: SFTP/file manager upload of `website/` to the webspace root for easyreportcreator.com. Enable HTTPS in the STRATO panel. PHP 8.x + `DirectoryIndex index.php`.

---

## 6. Design system (light)

Shared CSS variables in `shared/tokens.css`, consumed by `website/` and later `app/web/`.

| Token | Role | Light value (intent) |
|---|---|---|
| `--bg` | Page | Cool paper, not white-hot |
| `--panel` | Cards | White |
| `--text` | Body | Deep slate (`#16323f` family) |
| `--muted` | Secondary | Mid teal-grey |
| `--accent` | CTA / mono labels | Teal strong enough on white |
| `--success` / `--old` | Diff preview | Green / rose, WCAG on light |

The current demo UI (dark navy sidebar + light sheet) is replaced in WP7 so the **app and the site feel like one product**.

---

## 7. Acceptance (unchanged from the estimate)

1. Open `MN-P-RHN-PID-0001` in the app without AutoCAD running.
2. Dashboard / lists show the expected engineering data.
3. Header can include selected Project Details / custom properties + uploaded logo + revision table.
4. User can select columns from available class properties and save a template.
5. English Excel export for the core list types.
6. App starts with the provided run instructions on a machine that can see the project folder.
7. **Added:** easyreportcreator.com is live on STRATO in light UI matching the propertiesmanager.nl page format.

---

## 8. Explicitly not in these 100 hours

Live DCF write-back, Windows installer, cloud SSO / multi-tenant hosting, full 3D isometric lists, pixel-perfect Vitens `.xls` clone, extra UAT rounds, Node.js runtime on STRATO.
