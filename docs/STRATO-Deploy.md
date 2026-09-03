# STRATO deploy — EasyReportCreator (web app + DCF upload)

Locked with client **Tue 1 Sep 2026**: V1 is a **hosted web app on STRATO**. Users upload `ProcessPower.dcf`; analysis runs on the server. Desktop / no-upload remains a later option.

## What to upload

Upload the contents of **`website/`** to the webspace root for **easyreportcreator.com** (not the whole git repo).

Include:

| Path | Purpose |
|---|---|
| `*.php`, `inc/`, `assets/` | Public marketing site |
| `report/` | Report app UI + `api.php` |
| `report_templates/` | List templates (JSON) |
| `data/` | Runtime uploads (create writable; do not ship sample DCFs) |
| `.htaccess` | HTTPS redirect + directory index |

Do **not** upload `app/` (Python), `samples/`, or `reference/`.

## STRATO panel checklist

1. Point **easyreportcreator.com** (and www) at this webspace.
2. Enable **HTTPS / SSL**.
3. PHP **8.x** with extensions: **pdo_sqlite**, **sqlite3**, **zip**, **session**, **fileinfo**.
4. Raise upload limits if needed (typical `.dcf` is a few MB; sample ~3.3 MB):
   - `upload_max_filesize = 80M`
   - `post_max_size = 80M`
   - `max_execution_time = 120`
5. Ensure `website/data/uploads/` is **writable** by PHP and blocked from public HTTP (`.htaccess` Deny is created on first use).

## URLs

| URL | Role |
|---|---|
| `/` | Product site (Home, Product, Pricing, …) |
| `/report/` | Report app — upload `.dcf`, preview lists, export Excel |

## Privacy

Uploaded `.dcf` files are stored under the PHP session folder in `data/uploads/`. Document auto-cleanup / retention for ICT. Users should not upload confidential projects without an agreed retention policy.

## Local preview (optional)

```bat
cd website
php -S 127.0.0.1:8080
```

Open http://127.0.0.1:8080/ and http://127.0.0.1:8080/report/

## Python app (`app/`)

Kept for local development and regression tests (`app/scripts/`). Production path for V1 is the **PHP report app on STRATO**.
