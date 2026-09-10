# FB-001 — Engineering Items: all properties selectable (any project)

| Field | Value |
|---|---|
| **ID** | FB-001 |
| **Date** | 2026-09-09 |
| **Stage** | UAT-1 → UAT-2 (QA/QC) |
| **From** | Jan (Upwork + mail) |
| **Type** | Clarification / product gap (vs R9) |
| **Status** | Verify — implemented 2026-09-10; local tests passed on Vitens + P220049 Morssinkhof |
| **Requirements** | R9 (all P&ID/Plant 3D class properties selectable); R3; R6 |
| **Sample(s)** | `MN-P-RHN-PID-0001` (Vitens — regression); `P220049-Morssinkhof` / `samples/_incoming/ProcessPower.dcf` |

## Client message (paraphrase + key quotes)

> I have tried uploading a dcf file from another project but then i get errors or unknown properties. Maybe i wasn't clear on this, but **Vitens was a sample project**. All Plant 3D projects have **standard properties**, but user can define also properties. These properties are mostly in **Engineering Items** of the project. User must be able to **read all properties** and **make a selection for a list** he wants to generate. I have sent another sample by mail.

Jan also referenced Plant 3D context: **Engineering Items** as the primary class category holding shared/reportable properties; subclasses include Equipment, Inline Assets (valves), Instrumentation, Nozzles, Lines; non-engineering items are not the focus for reportable tags.

## Interpretation

1. **MN-P-RHN-PID-0001 / Vitens is a test fixture**, not the definition of available columns.
2. Column catalogues must be **discovered from the uploaded DCF** (`PnPTables` / `PnPProperties` / class hierarchy under Engineering Items), including **user-defined** properties.
3. Factory JSON templates may supply **defaults** for a list type, but the UI must offer **every property available on that project’s classes**, and survive properties that exist only on other projects (no hard failure / “unknown” for missing Vitens-only keys).
4. A **second sample project** is required for multi-project QA (awaiting file from mail → `samples/`).

## Root cause (current V1)

| Layer | Behaviour |
|---|---|
| Python `app/plant3d/catalog.py` | Already discovers class tree + properties from DCF metadata (dev/reference). |
| PHP `/report/` | Column picker is driven mainly by **fixed** `report_templates/*.json` (Vitens-oriented keys in SQL + templates). |
| Queries | `ErcQueries` SELECTs a **hard-coded** column set (incl. Vitens-specific fields). Missing columns on another DCF → SQL errors or empty/unknown keys in the grid. |

## Impact

- Blocks acceptance for “any Plant 3D project” until fixed.
- Thu 10 Sep handover should treat **FB-001** as the primary UAT-2 theme (or explicitly defer with Jan’s OK).
- Docs/marketing that imply full property pickers must match runtime once Catalog is wired in PHP.

## Acceptance criteria (when fixed)

- [x] Upload non-Vitens sample DCF → no SQL error on list open.
- [x] Column dialog lists **all** discoverable properties for the list’s Engineering Items class subtree (standard + user-defined).
- [x] User can show/hide any of those properties; preview + Excel use the selection.
- [x] Vitens sample still matches expected row counts (components **897** after pipe-membership filter).
- [x] Missing template keys that are not in the DCF are ignored (NULL) — not a hard crash.
- [x] Second sample documented under `samples/P220049-Morssinkhof/`.

## Work notes

- 2026-09-09: Feedback logged; Architecture + samples layout updated; `Catalog.php` stub added.
- 2026-09-10: Implemented `ErcCatalog`, resilient `ErcQueries`, API `property_catalogue` / `class_tree`, column UI catalogue. Tested with `scripts/test_fb001_samples.php` — ALL PASSED on Morssinkhof + Vitens.
