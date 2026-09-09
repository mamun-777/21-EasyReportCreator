# Public site + report app — easyreportcreator.com

PHP 8 product site **and** hosted report engine for STRATO. Light theme only.

**Deployment (locked Tue 1 Sep 2026):** users **upload** `ProcessPower.dcf` in the browser. See `docs/STRATO-Deploy.md`.

## Layout

| Path | Purpose |
|---|---|
| `index.php` … `terms.php` | Marketing pages |
| `report/` | Report app UI + `api.php` |
| `inc/plant3d/` | DCF, queries, templates, Excel (PDO SQLite) |
| `report_templates/` | List JSON templates (synced from `app/report_templates`) |
| `data/uploads/` | Session upload storage (writable on STRATO; not public) |

## Pages

| File | Purpose |
|---|---|
| `index.php` | Home |
| `product.php` | Features, workflow, requirements |
| `pricing.php` | Version 1 package vs later add-ons |
| `download.php` | How to open the web app |
| `contact.php` | Support |
| `terms.php` | Delivery scope |
| `report/login.php` | Company sign-in / register |
| `report/index.php` | Upload → lists → Excel (auth required) |

## Local preview

```bat
cd website
php -S 127.0.0.1:8080
```

http://127.0.0.1:8080/ · http://127.0.0.1:8080/report/

Needs PHP 8 with `pdo_sqlite` and `zip`.

## STRATO

Upload **contents of this folder** to the domain root. Do not upload `../app/` or `../samples/`. The Python app under `app/` remains for local regression tests only.
