# STRATO deploy — EasyReportCreator (web app + DCF upload)

Locked with client **Tue 1 Sep 2026**: V1 is a **hosted web app on STRATO**. Users upload `ProcessPower.dcf`; analysis runs on the server. Desktop / no-upload remains a later option.

**End-user steps:** see [`User-Guide.md`](User-Guide.md).  
**Client acceptance checklist:** see [`Handover-Package.md`](Handover-Package.md).

## Live deploy status (Wed 9 Sep 2026)

| Item | Value |
|---|---|
| Server | `WIN-UBFMCPEROJ3` · Windows Server 2025 · `217.154.240.82` |
| Site path | `C:\inetpub\easyreportcreator` |
| IIS site | **EasyReportCreator** (separate from existing **ShapeDevelopAPI**) |
| PHP | **8.3.33 NTS** at `C:\PHP` (pdo_sqlite, sqlite3, zip, mbstring, …) |
| DNS | `easyreportcreator.com` / `www` → `217.154.240.82` |
| HTTP | `http://easyreportcreator.com/` and `http://217.154.240.82:8080/` |
| HTTPS | **Live** — Let's Encrypt via win-acme (`CN=easyreportcreator.com`); Task Scheduler renew |
| Fonts | Self-hosted woff2 under `assets/fonts/` (Space Grotesk, Inter, JetBrains Mono) |
| Plesk | License present; **not installed** — production uses **IIS + PHP FastCGI** |

**Do not** overwrite `C:\inetpub\wwwroot\Api` (ShapeDevelop).

### Redeploy (from this PC)

```powershell
powershell -File scripts\pack-website-deploy.ps1
# Then copy zip contents into C:\inetpub\easyreportcreator (keep data\auth.sqlite / companies if present)
```

Or sync changed files over SSH/SFTP into `C:\inetpub\easyreportcreator`. After PHP/IIS changes, recycle the **EasyReportCreator** app pool if needed.

## What to upload

Upload the contents of **`website/`** to the IIS site root (`C:\inetpub\easyreportcreator`) — **not** the whole git repo.

**Package locally:**

```powershell
powershell -File scripts\pack-website-deploy.ps1
```

Creates `dist/easyreportcreator-website-YYYYMMDD-HHMM.zip` (no `auth.sqlite`, no uploaded `.dcf`).

Include:
| `report/` | Report app UI + `api.php` |
| `report_templates/` | List templates (JSON) |
| `data/` | Runtime uploads (writable; do not ship sample DCFs) |
| `web.config` | IIS: HTTPS redirect, 80 MB upload limit, DirectoryIndex |
| `.htaccess` / `.user.ini` | Apache / PHP-FPM limits when applicable |

Do **not** upload `app/` (Python), `samples/`, or `reference/`.

## Plesk checklist (optional)

Plesk is **not required** for the current V1 host (IIS + PHP). If you install Plesk later:

1. Point domain **easyreportcreator.com** (and www) at this VPS.
2. Enable HTTPS (Let’s Encrypt).
3. PHP 8.x with **pdo_sqlite**, **sqlite3**, **zip**, **session**, **fileinfo**, **json**.
4. Upload limits: `upload_max_filesize` / `post_max_size` = 80M (IIS `web.config` already sets 80 MB).
5. Ensure `data/`, `data/uploads/`, `data/companies/` are writable by the site identity; `data/auth.sqlite` must be writable (not owned read-only by Administrator).
6. Confirm `data/` is not publicly downloadable (`web.config` deny + `.htaccess` deny).

### IIS write access (required)

PHP on this host may run as **`NT AUTHORITY\IUSR`** (anonymous auth) and/or **`IIS APPPOOL\EasyReportCreator`**. Grant Modify on `data/` to both, and prefer empty anonymous `userName` (application pool identity):

```powershell
icacls C:\inetpub\easyreportcreator\data /grant "NT AUTHORITY\IUSR:(OI)(CI)(M)" /T
icacls C:\inetpub\easyreportcreator\data /grant "IIS APPPOOL\EasyReportCreator:(OI)(CI)(M)" /T
icacls C:\inetpub\easyreportcreator\data /grant "IIS_IUSRS:(OI)(CI)(M)" /T
%windir%\system32\inetsrv\appcmd.exe set config "EasyReportCreator" -section:system.webServer/security/authentication/anonymousAuthentication /userName:"" /password:"" /commit:apphost
```

Without this, register/login fails with `unable to open database file` or `attempt to write a readonly database`.

Also set `existingResponse="PassThrough"` on `httpErrors` in `web.config` so API JSON errors are not replaced by IIS HTML 500 pages.

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
| `/report/register.php` | Create company account |

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
