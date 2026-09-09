# STRATO deploy — EasyReportCreator (web app + DCF upload)

Locked with client **Tue 1 Sep 2026**: V1 is a **hosted web app on STRATO**. Users upload `ProcessPower.dcf`; analysis runs on the server. Desktop / no-upload remains a later option.

## Live deploy status (Tue 8 Sep 2026)

| Item | Value |
|---|---|
| Server | `WIN-UBFMCPEROJ3` · Windows Server 2025 · `217.154.240.82` |
| Site path | `C:\inetpub\easyreportcreator` |
| IIS site | **EasyReportCreator** (separate from existing **ShapeDevelopAPI**) |
| PHP | **8.3.33 NTS** at `C:\PHP` (pdo_sqlite, sqlite3, zip, mbstring, …) |
| HTTP now | `http://easyreportcreator.com/` and `http://217.154.240.82:8080/` |
| Host headers | `easyreportcreator.com` / `www` on ports **80** and **443** |
| HTTPS | **Live** — Let's Encrypt via win-acme (`CN=easyreportcreator.com`); auto-renew scheduled |
| Plesk | License present; **not installed** — deploy uses IIS + PHP directly |

**Do not** overwrite `C:\inetpub\wwwroot\Api` (ShapeDevelop).


## What to upload

Upload the contents of **`website/`** to the domain document root for **easyreportcreator.com** (Plesk `httpdocs` / IIS site root — **not** the whole git repo).

**Package locally:**

```powershell
powershell -File scripts\pack-website-deploy.ps1
```

Creates `dist/easyreportcreator-website-YYYYMMDD-HHMM.zip` (no `auth.sqlite`, no uploaded `.dcf`).

Include:

| Path | Purpose |
|---|---|
| `*.php`, `inc/`, `assets/` | Public marketing site |
| `report/` | Report app UI + `api.php` |
| `report_templates/` | List templates (JSON) |
| `data/` | Runtime uploads (writable; do not ship sample DCFs) |
| `web.config` | IIS: HTTPS redirect, 80 MB upload limit, DirectoryIndex |
| `.htaccess` / `.user.ini` | Apache / PHP-FPM limits when applicable |

Do **not** upload `app/` (Python), `samples/`, or `reference/`.

## Plesk checklist (Windows VPS)

1. In Plesk: add / point domain **easyreportcreator.com** (and www) at this VPS.
2. Enable **HTTPS / SSL** (Let’s Encrypt in Plesk).
3. **PHP 8.x** with extensions: **pdo_sqlite**, **sqlite3**, **zip**, **session**, **fileinfo**, **json**.
4. Raise upload limits (typical `.dcf` a few MB; sample ~3.3 MB; app allows up to 80 MB):
   - Plesk → PHP settings: `upload_max_filesize = 80M`, `post_max_size = 80M`, `max_execution_time = 120`, `memory_limit = 256M`
   - IIS: `web.config` already sets `maxAllowedContentLength` to 80 MB
   - `website/.user.ini` mirrors the same values when PHP reads user.ini
5. Ensure `data/`, `data/uploads/`, `data/companies/` are **writable** by the IIS / Plesk PHP identity.
6. Confirm `data/` is **not** publicly downloadable (`web.config` deny + `.htaccess` deny).
7. Factory JSON in `report_templates/` can stay read-only; company standards land under `data/companies/`.

## STRATO customer portal

- Overview / Firewall / Domains / Security as needed for DNS and ports 80/443 (and 3389 only if RDP is required).
- Prefer **Plesk File Manager** or **FTP/SFTP** for file deploy over raw RDP file copy.

## Feature parity (report app)

- Company **login / logout / register**
- Title-bar: Header setup, Logo, Save profile, Export Excel (options remembered per company)
- Columns remain **per list**
- Company logo + header defaults apply to **all lists**
- In-app **notifications** (toast)
- DCF upload, 8 lists, Excel export
- **Session cleanup:** uploaded `.dcf` removed on logout / clear; orphan upload folders purged after **24 hours**

Deferred: Excel import → demo DCF write-back; NL toggle; Flatpickr.

## URLs

| URL | Role |
|---|---|
| `/` | Product site (Home, Product, Pricing, …) |
| `/report/` | Report app — upload `.dcf`, preview lists, export Excel |
| `/report/login.php` | Sign in |

## Privacy

Uploaded `.dcf` files live under `data/uploads/{session}/`. They are deleted on **logout** / **clear**, and stale folders are removed after **24 h**. Document this retention for ICT. Users should not upload confidential projects without an agreed policy.

## Smoke test after deploy

1. Open `https://easyreportcreator.com/` — marketing pages load over HTTPS.
2. Open `/report/register.php` — create a test company account.
3. Upload sample `ProcessPower.dcf` (~3.3 MB) — progress bar completes; lists show counts.
4. Open Valve list → Export Excel — file downloads with title block / logo options.
5. Log out — confirm session project is cleared (re-login needs a fresh upload).

## Local preview (optional)

```bat
cd website
.tools\php\php.exe -d upload_max_filesize=80M -d post_max_size=80M -S 127.0.0.1:8080
```

Open http://127.0.0.1:8080/ and http://127.0.0.1:8080/report/

## Python app (`app/`)

Kept for local development and regression tests (`app/scripts/`). Production path for V1 is the **PHP report app on STRATO**.
