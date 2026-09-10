# EasyReportCreator — Weekly and daily schedule

| Field | Value |
|---|---|
| Start | **Wednesday 19 August 2026** |
| Finish | **Thursday 10 September 2026** |
| Deliverable budget | **100 hours** (WP total — unchanged) |
| Upwork calendar | **17 working days × 8 h = 136 h** (3 weeks + 2 days) |
| Week shape | **5 working days per week**, Mon–Fri blocks (Wed-start); **+2 days** Wed 9 Sep + Thu 10 Sep |
| Daily pace | **8 h every working day** (4 h AM + 4 h PM on Upwork) |
| **Deployment (locked Tue 1 Sep)** | **Web app on STRATO** — user **uploads** `ProcessPower.dcf`; no desktop installer / code signing for V1 |

Each week is a **5-day block** (Wed → Tue) because the project starts mid-week. Weekends are always off. **Wed 9 Sep** and **Thu 10 Sep** are extra working days for STRATO hosted-app deploy and handover.

Upwork copy-paste lines: **`Upwork-Time-Log.md`** and **section 4** below (≤ 120 characters each).

---

## 1. Hour budget (100 h deliverable — unchanged)

| WP | Deliverable | Hours |
|---|---|---:|
| WP1 | Baseline, branding, light design tokens, repo layout | **5** |
| WP2 | Header from Project Details (standard + user-defined) | **12** |
| WP3 | Property / column selection from class catalogue | **20** |
| WP4 | Templates / company standards | **8** |
| WP5 | One-way English Excel (logo, title block, revision) | **10** |
| WP6 | Core list types on `MN-P-RHN-PID-0001` | **11** |
| WP7 | English light web UI (propertiesmanager.nl format) | **12** |
| WP8 | **STRATO hosted web app** (DCF upload) + easyreportcreator.com + HTTPS | **10** |
| WP9 | Short user guide | **3** |
| WP10 | Acceptance on sample + one feedback round | **7** |
| — | Coordination / check-ins | **2** |
| | **Total** | **100** |

The **136 h calendar** (17 × 8 h) includes the **+2 days** (Wed 9 Sep + Thu 10 Sep) for STRATO deploy, upload flow hardening, and handover while you log a full 8 h day on Upwork every working day.

---

## 2. Weekly view (3 × 5 days + 1 day × 8 h)

| Week | Dates (working days) | Upwork h | Deliverable focus |
|---|---|---:|---|
| **1** | Wed 19 – Tue 25 Aug | **40** | Architecture, PHP site, Project Details header |
| **2** | Wed 26 Aug – Tue 1 Sep | **40** | Column picker, templates, Excel, first lists |
| **3** | Wed 2 – **Thu 10 Sep** | **56** | Remaining lists, light UI, **STRATO web app + DCF upload**, docs, UAT / handover |
| | | **136** | **100 h** scope complete by **Thu 10 Sep** |

---

## 3. Daily view (16 × 8 h)

### Week 1 — Wed 19 – Tue 25 Aug (40 h)

| Date | h | WP | Work |
|---|---:|---|---|
| **Wed 19 Aug** | 8 | WP1 | Kick-off. PHP+Python split, light theme, domain, STRATO. Architecture doc, schedule, repo layout. |
| **Thu 20 Aug** | 8 | WP1, WP8 | Shared light tokens. PHP config, header, footer. Home page + local preview script. |
| **Fri 21 Aug** | 8 | WP8 | Product, Pricing, Download, Contact, Terms. DNS checklist for easyreportcreator.com. |
| **Mon 24 Aug** | 8 | WP2 | Read PnPProject / Project Details + all custom categories (S88). Header catalogue API. |
| **Tue 25 Aug** | 8 | WP2 | Header field picker, logo upload, revision table in preview. Week 1 wrap-up. |

### Week 2 — Wed 26 Aug – Tue 1 Sep (40 h)

| Date | h | WP | Work |
|---|---:|---|---|
| **Wed 26 Aug** | 8 | WP3 | Class property catalogue from sample project. **Week 1 check-in.** |
| **Thu 27 Aug** | 8 | WP3 | Column picker UI per list/class; visible/hidden; English headers. |
| **Fri 28 Aug** | 8 | WP3, WP4 | Apply selection to queries. Template save/reload started. |
| **Mon 31 Aug** | 8 | WP4 | Company-standard templates: create, overwrite, load. Eight list defaults. |
| **Tue 1 Sep** | 8 | WP5 | Excel export: logo, title block, revision table, English issue layout. **Client locks web app + DCF upload on STRATO.** |

### Week 3 — Wed 2 – Thu 10 Sep (56 h)

| Date | h | WP | Work |
|---|---:|---|---|
| **Wed 2 Sep** | 8 | WP6 | Valve, equipment, line lists on sample project. **Week 2 check-in.** |
| **Thu 3 Sep** | 8 | WP6 | Control valve, instrument, drawing, line summary lists. |
| **Fri 4 Sep** | 8 | WP6, WP7 | Componentenlijst. Start light app UI (top nav, shared tokens). |
| **Mon 7 Sep** | 8 | WP7, WP8 | Finish light UI. Flow polish: upload → list → columns → preview → export. |
| **Tue 8 Sep** | 8 | WP8 | Deploy hosted web app on STRATO. DCF upload, session storage, HTTPS smoke-test. |
| **Wed 9 Sep** | 8 | WP8–WP10 | Deploy notes, user guide (upload workflow). Acceptance via web upload. |
| **Thu 10 Sep** | 8 | WP10 / FB-001 | UAT-2: multi-project Engineering Items properties (Jan feedback). Final polish + handover as agreed. |

---

## 4. Upwork time log (8 h/day — 4 h AM + 4 h PM)

| Date | h | AM (4 h) | PM (4 h) |
|---|---:|---|---|
| Wed 19 Aug | 8 | Kick-off. Lock PHP+Python split, light theme, domain. Draft architecture doc. | Delivery schedule + repo layout (app/, samples/, reference/). Project structure doc. |
| Thu 20 Aug | 8 | Shared light design tokens. PHP config, header, footer includes. | Home page in PHP. Local preview script. Start Product page. |
| Fri 21 Aug | 8 | Product + Pricing pages. Stylesheet polish on public site. | Download, Contact, Terms pages. DNS checklist for easyreportcreator.com. |
| Mon 24 Aug | 8 | Read PnPProject + Project Details from sample DCF. Standard header fields. | Parse custom categories (S88 etc). Header catalogue API endpoints. |
| Tue 25 Aug | 8 | Header field picker UI. Wire fields into preview title block. | Logo upload + preview. Revision table editor. Week 1 wrap-up testing. |
| Wed 26 Aug | 8 | Class property discovery from sample project. Engineering Items tree. | Property list per class via API. Week 1 check-in with client. |
| Thu 27 Aug | 8 | Column picker UI: show/hide toggles. English column header labels. | Connect picker to preview grid. Persist column selection in app state. |
| Fri 28 Aug | 8 | Apply selected columns to query output. Hide fields from preview/export. | Template save: columns + sort order. Start template load/reload JSON flow. |
| Mon 31 Aug | 8 | Template create, overwrite, load. Defaults for eight list types. | Save header + revision rows in template. Valve list round-trip test. |
| Tue 1 Sep | 8 | Excel title block from selected Project Details. Logo in workbook. | Revision table in Excel. English issued layout. Client confirms STRATO web + upload. |
| Wed 2 Sep | 8 | Harden valve list on MN-P-RHN-PID-0001. Validate row counts vs sample. | Equipment + line lists hardened. Week 2 check-in with client. |
| Thu 3 Sep | 8 | Control valve + instrument lists on sample project. | Drawing list + line summary export tests on sample DCF. |
| Fri 4 Sep | 8 | Componentenlijst query + Excel export. Regression on all eight lists. | Light UI: shared tokens, Space Grotesk title bar, sidebar counts. |
| Mon 7 Sep | 8 | Matched public site tokens/type. Project card + list-count dashboard. | Flow polish: upload zone, steps, empty states, drag-drop → export. |
| Tue 8 Sep | 8 | Remote VPS: IIS site, PHP 8.3 FastCGI, 80 MB uploads, session cleanup. | Deployed to STRATO; smoke-test OK. DNS/HTTPS when A-record ready. |
| Wed 9 Sep | 8 | Deploy notes + short user guide (upload workflow). | Acceptance on MN-P-RHN-PID-0001 via web upload. Package for Jan feedback. |
| Thu 10 Sep | 8 | FB-001: live property catalogue from DCF (any project). | Verify on 2nd sample + handover / notes for Jan. |

---

## 5. Domain and STRATO (inside WP8)

**Deployment model:** single **web app on STRATO** — users upload `ProcessPower.dcf` in the browser (typical file size a few MB; sample ~3.3 MB). No desktop installer or Windows code signing for V1.

1. **DNS** — point easyreportcreator.com (and www) at the STRATO webspace.
2. **SSL** — enable HTTPS in the STRATO panel.
3. **PHP** — PHP 8.x, `DirectoryIndex index.php`; tune `upload_max_filesize` / `post_max_size` for `.dcf` uploads.
4. **Deploy** — public pages + report engine on STRATO (upload → analyse → preview → Excel export).
5. **Privacy** — session-based storage; auto-delete uploaded `.dcf` after use (document in user guide).

---

## 6. Daily rhythm

1. Log **8 h on Upwork** every working day (4 h AM + 4 h PM).
2. Prove engine changes on `samples/MN-P-RHN-PID-0001` the same day (local dev until STRATO deploy on Tue 8 Sep).
3. Check-ins: end of **Wed 26 Aug**, **Wed 2 Sep**, **Thu 10 Sep** (handover).

---

## 7. Slip rule

If scope slips, use **Tue 8 – Thu 10 Sep** before dropping WP3 (property selection). Do not cut the 8 h working-day shape. The **+2 days (Wed 9 + Thu 10 Sep)** are reserved for STRATO hosted-app deploy and handover.
