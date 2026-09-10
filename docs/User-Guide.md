# EasyReportCreator — user guide (V1)

**Audience:** Engineers issuing Plant 3D lists  
**App:** https://easyreportcreator.com/report/  
**Sample acceptance project:** `MN-P-RHN-PID-0001` (Productiebedrijf Rhenen)

Plant 3D does **not** need to be running. You only need the project database file `ProcessPower.dcf`.

---

## 1. Sign in

1. Open https://easyreportcreator.com/report/login.php  
2. First time: use **Create account** — enter company name, your name, email, and password.  
3. Later visits: sign in with email and password.

Each company has its own logo, header defaults, and saved list templates.

---

## 2. Upload ProcessPower.dcf

1. In the left **Project** panel, click **Upload .dcf** (or drop the file on the upload zone).  
2. Choose `ProcessPower.dcf` from your Plant 3D project folder (typical size a few MB; maximum 80 MB).  
3. Wait until the upload finishes and the project card shows the project name / number.  
4. List counts appear next to each list in the sidebar.

**Tip:** For the Rhenen sample, use  
`samples/MN-P-RHN-PID-0001/ProcessPower.dcf` from the delivery package (local copy).

---

## 3. Choose a list

Click a list in the sidebar, for example:

| List | Typical use |
|---|---|
| Valve List | Hand valves |
| Equipment List | Equipment |
| Line List | Pipe lines |
| Control Valve List | Control valves |
| Instrument List | Instruments |
| Drawing List | Drawings |
| Line Summary | Line groups |
| Component List | Componentenlijst (non–pipe-line components) |

The preview shows the title block, revision table, and rows.

---

## 4. Columns (optional, per list)

1. Click **Columns**.  
2. Show or hide properties for this list.  
3. Apply — choices stay with this list (company standard when you save).

Company **logo**, **header fields**, and **export options** apply to **every** list.

---

## 5. Header and logo (company-wide)

| Action | What it does |
|---|---|
| **Header setup** | Pick Project Details fields, document number, revision table |
| **Upload logo** | PNG/JPG shown in preview and Excel |
| **Save profile** | Store company header / logo / export prefs, or save the current list as company standard |

---

## 6. Export Excel

1. Click **Export Excel**.  
2. Choose whether to include logo, revision history, and PnPID.  
3. Optionally remember these options for next time.  
4. Confirm — an English `.xlsx` downloads for the **current** list.

Repeat for each list you need to issue.

---

## 7. Log out

Click **Log out**. The uploaded `.dcf` for this session is removed from the server. Sign in again and upload if you continue later.

---

## Privacy (ICT)

- Uploaded databases are stored only under your signed-in session folder on the server.  
- They are deleted on **logout** / clear, and orphan folders are removed after **24 hours**.  
- Do not upload confidential projects unless your organisation accepts this retention policy.

---

## Troubleshooting

| Problem | What to try |
|---|---|
| Upload fails / file too large | Confirm the file is `.dcf` and under 80 MB. Retry on a stable connection. |
| “Upload a project first” | Upload `ProcessPower.dcf` before opening lists or exporting. |
| Empty list / unexpected counts | Confirm you uploaded the correct project database. |
| Logo missing in Excel | Upload logo, then export again with “Include company logo” checked. |
| Cannot sign in | Use **Create account**, or ask your admin to register your company email. |

---

## Related docs

- Deploy / server notes: `docs/STRATO-Deploy.md`  
- Schedule / acceptance: `docs/Delivery-Schedule.md`
