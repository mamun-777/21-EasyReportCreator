# Sample Plant 3D projects

Local acceptance and QA fixtures. **Not** deployed to STRATO. `.dcf` files are gitignored.

| Folder | Role |
|---|---|
| `MN-P-RHN-PID-0001/` | **Regression sample** — Productiebedrijf Rhenen (Vitens PnId V6.1). Used for V1 count checks. **Not** the definition of available properties. |
| `P220049-Morssinkhof/` | **FB-001 multi-project sample** — P220049 Morssinkhof (from Jan by mail). Different Engineering Items properties. |
| `_incoming/` | Drop zone for client-emailed projects before they are named and documented. |

## How to add a new sample

1. Create `samples/<ProjectFolder>/` (use Plant 3D project folder name when known).
2. Copy `ProcessPower.dcf` (and optional notes) into that folder.
3. Add `samples/<ProjectFolder>/README.md` with project name, source (client/mail), and QA purpose.
4. Register the folder in the table above and in the relevant `docs/qa/FB-*.md` ticket.
5. Prefer regression scripts that take an explicit DCF path (do not hard-code Vitens-only columns).

## QA rule (FB-001)

Property discovery and column selection must work for **any** uploaded Plant 3D DCF. Vitens proves list **counts** and default templates; other samples prove **generic Engineering Items properties**.
