# EasyReportCreator — Weekly and daily schedule

| Field | Value |
|---|---|
| Start | **Wednesday 19 August 2026** |
| Finish | **Tuesday 8 September 2026** |
| Deliverable budget | **100 hours** (WP total — unchanged) |
| Upwork calendar | **15 working days × 8 h = 120 h** (3 full weeks) |
| Week shape | **5 working days per week**, Mon–Fri blocks (Wed-start) |
| Daily pace | **8 h every working day** (4 h AM + 4 h PM on Upwork) |

Each week is a **5-day block** (Wed → Tue) because the project starts mid-week. Weekends are always off.

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
| WP8 | Local run + STRATO PHP site + easyreportcreator.com | **10** |
| WP9 | Short user guide | **3** |
| WP10 | Acceptance on sample + one feedback round | **7** |
| — | Coordination / check-ins | **2** |
| | **Total** | **100** |

The **120 h calendar** (15 × 8 h) gives room for regression testing, deploy friction, and check-ins while you log a full 8 h day on Upwork every working day.

---

## 2. Weekly view (3 × 5 days × 8 h)

| Week | Dates (5 days each) | Upwork h | Deliverable focus |
|---|---|---:|---|
| **1** | Wed 19 – Tue 25 Aug | **40** | Architecture, PHP site, Project Details header |
| **2** | Wed 26 Aug – Tue 1 Sep | **40** | Column picker, templates, Excel, first lists |
| **3** | Wed 2 – Tue 8 Sep | **40** | Remaining lists, light UI, STRATO, docs, UAT / handover |
| | | **120** | **100 h** scope complete by Tue 8 Sep |

---

## 3. Daily view (15 × 8 h)

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
| **Tue 1 Sep** | 8 | WP5 | Excel export: logo, title block, revision table, English issue layout. |

### Week 3 — Wed 2 – Tue 8 Sep (40 h)

| Date | h | WP | Work |
|---|---:|---|---|
| **Wed 2 Sep** | 8 | WP6 | Valve, equipment, line lists on sample project. **Week 2 check-in.** |
| **Thu 3 Sep** | 8 | WP6 | Control valve, instrument, drawing, line summary lists. |
| **Fri 4 Sep** | 8 | WP6, WP7 | Componentenlijst. Start light app UI (top nav, shared tokens). |
| **Mon 7 Sep** | 8 | WP7, WP8 | Finish light UI. Flow polish. STRATO upload + HTTPS smoke-test. |
| **Tue 8 Sep** | 8 | WP8–WP10 | Deploy notes, user guide, acceptance on sample project. **Handover.** |

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
| Tue 1 Sep | 8 | Excel title block from selected Project Details. Logo in workbook. | Revision table in Excel. English issued layout + column header export. |
| Wed 2 Sep | 8 | Harden valve list on MN-P-RHN-PID-0001. Validate row counts vs sample. | Equipment + line lists hardened. Week 2 check-in with client. |
| Thu 3 Sep | 8 | Control valve + instrument lists on sample project. | Drawing list + line summary export tests on sample DCF. |
| Fri 4 Sep | 8 | Componentenlijst query + export. Regression on all eight list types. | Replace dark sidebar with light top nav. Apply shared tokens in app UI. |
| Mon 7 Sep | 8 | Match public site typography/cards. Dashboard + project card polish. | Flow polish: open → list → columns → preview → export. STRATO upload + HTTPS. |
| Tue 8 Sep | 8 | Local run script + deploy notes. Short user guide draft. | Acceptance on MN-P-RHN-PID-0001. Package for Jan feedback round. Handover. |

---

## 5. Domain and STRATO (inside WP8)

1. **DNS** — point easyreportcreator.com (and www) at the STRATO webspace.
2. **SSL** — enable HTTPS in the STRATO panel.
3. **PHP** — PHP 8.x, `DirectoryIndex index.php`.
4. **Upload** — contents of `website/` to the domain root.
5. **App** — not uploaded; local run next to `ProcessPower.dcf`.

---

## 6. Daily rhythm

1. Log **8 h on Upwork** every working day (4 h AM + 4 h PM).
2. Prove engine changes on `samples/MN-P-RHN-PID-0001` the same day.
3. Check-ins: end of **Wed 26 Aug**, **Wed 2 Sep**, **Tue 8 Sep** (handover).

---

## 7. Slip rule

If scope slips, use **Mon 7 – Tue 8 Sep buffer** before dropping WP3 (property selection). Do not cut the 5-day week shape or log fewer than 8 h on a working day.
