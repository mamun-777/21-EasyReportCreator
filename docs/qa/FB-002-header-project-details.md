# FB-002 — Report header not updating from uploaded project

| Field | Value |
|---|---|
| **ID** | FB-002 |
| **Date** | 2026-09-11 |
| **Stage** | UAT-2 / post FB-001 |
| **From** | Jan (Upwork) |
| **Type** | Bug |
| **Status** | Verify — fixed 2026-09-11; tested on Morssinkhof + Vitens; deployed live |
| **Requirements** | R8 (header from Project Details + custom properties) |
| **Sample(s)** | `P220049-Morssinkhof` (sidebar shows correct project; header showed stale MN-O-STH / Vitens fields) |

## Client message

> Hi Mamun, thanks for the update. I have tried uploading another project and it looks okay. Only thing i see is that header is not updated with client / project information. Regards, Jan

## Evidence

Sidebar: **P220049 Morssinkhof** / MBR / ProcessPower.dcf  
Title block still showed e.g. Project name/number **MN-O-STH-PID-0001**, document **MN-P-RHN-PID-0001-A1**, demo revision text — not the uploaded project’s PnPProject details.

## Interpretation

1. List data / counts for the new project are OK (FB-001).
2. Title-block **field keys** may come from company defaults (correct), but **values** were kept from company profile / previous apply / factory templates instead of the current DCF.
3. Document number and revision table also stayed on sample defaults.

## Root cause

`ErcProject::mergeHeader()` used `$field['value'] ?? $values[$key]`, so any baked-in value on the template/company profile blocked live `PnPProject` values.

## Acceptance criteria

- [x] After upload of Morssinkhof (or any DCF), Valve List title block shows that project’s Project_Name / Project_Number / description (and other selected keys that exist).
- [x] Document number reflects the current project number (not Vitens sample).
- [x] Revision table prefers project revisions when present; no stale “Demo issue from ProcessPower” from another project.
- [x] Company name (account) may remain the user’s company (e.g. TSpD BV).
- [x] Vitens sample header still fills correctly from its own PnPProject.

## Work notes

- 2026-09-11: Logged; fixed `mergeHeader` to always use live PnPProject values; strip values from company profile field list; document number + stale demo revision refresh; `scripts/test_fb002_header.php` PASSED; deployed.
