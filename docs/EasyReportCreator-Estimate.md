# EasyReportCreator — Fixed Proposal (90–100 hours)

| Field | Value |
|---|---|
| Document | Commercial proposal / delivery plan |
| Product | **EasyReportCreator** |
| Customer | Jan |
| Prepared / revised | 20 August 2026 |
| Agreed budget | **90–100 hours** (target **95 hours**) |
| Delivery form | **Web application** |
| Basis | Existing demo, `docs/EasyReportCreator-Requirements.md`, RFI answers, web-app reconfirmation |

---

## 1. Proposal summary

Within **90–100 hours**, EasyReportCreator will be delivered as a usable **web app** that covers the agreed Version 1 needs, building on the working demo already in place.

| Item | Commitment |
|---|---|
| **Budget** | **90–100 hours** (quote target **95 h**) |
| **Product** | Web app (browser + local/intranet backend) |
| **Language** | English issued lists |
| **Excel** | One-way export with logo + revision table |
| **Write-back** | Not in this package (optional Pro later) |
| **Windows installer** | Not in this package |

---

## 2. Locked customer decisions (unchanged)

| Topic | Decision |
|---|---|
| Deployment | **Web app** (not standalone Windows) |
| Export / write-back | **One-way Excel** for now; Pro write later if ordered |
| Future write rules | User-defined properties only; AutoCAD closed; preview + approval |
| Language | **English** only |
| AutoCAD plugin | No |

---

## 3. What is included in the 90–100 hour package

All of the items below are included. Scope is sized to finish inside the budget by reusing the existing demo and keeping each area production-usable (not over-engineered).

| # | Deliverable | Hours | What you get |
|---|---|---:|---|
| WP1 | Baseline & branding | **3** | EasyReportCreator naming; clean product baseline from current demo |
| WP2 | Header from Project Details | **12** | Standard + user-defined project properties (e.g. S88) into title block; logo upload |
| WP3 | Property / column selection | **22** | For each supported list/class, user can choose which properties appear as columns (standard + user-defined available on that class in the sample project) |
| WP4 | Templates / company standards | **9** | Save & reload column set, sort, header fields, revision rows |
| WP5 | Excel export (one-way) | **10** | English Excel with logo, title block, revision table; ready to issue |
| WP6 | Core list types | **12** | Valve, control valve, equipment, line, line summary, instrument, drawing, Componentenlijst on `MN-P-RHN-PID-0001` |
| WP7 | English web UI | **10** | Clear flow: open project → choose list → pick columns → preview → export |
| WP8 | Web deploy | **5** | Run script + short deploy notes for local / intranet use |
| WP9 | Documentation | **3** | Short user guide (how to open project, set template, export) |
| WP10 | Test & feedback fixes | **7** | Acceptance on sample project + one feedback round |
| — | Coordination | **2** | Short check-ins / clarification within the package |
| | **Total** | **95** | Fits inside **90–100 h** |

### How this fits the budget

- The current demo already provides DCF reading, list queries, basic templates, Excel export, and UI — so this package is **completion + hardening**, not a greenfield build.
- Property selection is delivered for the **supported list types / classes used in the sample project**, not a research project into every possible Plant 3D edge case.
- One feedback round is included; large new feature requests after UAT move to a follow-up package.

---

## 4. Explicitly out of this 90–100 hour package

| Item | Status |
|---|---|
| Live write-back into Plant 3D / DCF | Later **Pro** package (~40–65 h) |
| Native Windows installer (MSI/exe) | Optional later (~10–20 h) |
| Public cloud hosting + SSO / multi-tenant | Optional later |
| Full 3D piping / isometric lists | Later (unless added by change request) |
| Pixel-perfect match to every historical Vitens `.xls` layout | Best-effort English issued layout; exact clone only if a sample is provided and fits remaining hours |
| Unlimited property discovery for every obscure Plant 3D class worldwide | Covered for sample-project classes / agreed list types |
| Extra UAT rounds beyond one feedback cycle | Change request / extra hours |

---

## 5. Acceptance criteria (done when…)

1. User can open the Rhenen sample project in the web app without AutoCAD running.
2. Dashboard / lists show the expected engineering data for that project.
3. Header can include selected Project Details / custom properties + uploaded logo + revision table.
4. User can select columns from available class properties and save that as a template.
5. User can export English Excel for the core list types above.
6. App can be started with the provided run/deploy instructions on a local or intranet machine that can see the project folder.

---

## 6. Timeline (inside 90–100 hours)

| Phase | Hours | Calendar (focused) | Deliverable |
|---|---:|---|---|
| A — Align remaining details | 4 | 1–2 days | Logo, paper size, master list preference (if still open) |
| B — Header + property selection + templates | 43 | ~1–1.5 weeks | Core engine working on sample |
| C — Lists + Excel + English UI | 32 | ~1–1.5 weeks | Export-ready lists |
| D — Deploy, docs, test, feedback | 16 | ~0.5–1 week | Handover + fixes |
| **Total** | **95** | **about 3–4 weeks** | |

*(Part-time / Upwork pace: about **3.5–5 weeks**.)*

---

## 7. Assumptions

1. Work continues from the existing EasyReportCreator demo codebase.
2. Acceptance is against **`MN-P-RHN-PID-0001`** (P&ID).
3. Backend can access the project folder / `ProcessPower.dcf` (local path or mapped share).
4. Customer supplies logo (and preferred document number / paper size if different from demo defaults).
5. One consolidated feedback round after the first complete demo of this package.
6. Scope changes (write-back, 3D, cloud SSO, Windows installer) are quoted separately.

---

## 8. Optional add-ons (after this package)

| Add-on | Hours |
|---|---:|
| Pro write-back (user-defined props only; AutoCAD closed; preview + approval) | 40–65 |
| Windows installer wrapper | 10–20 |
| Cloud / SSO hosting | 20–40 |
| 3D list support | 30–55 |

---

*This is the active commercial plan for the 90–100 hour engagement. Larger earlier hour bands are superseded by this document.*
