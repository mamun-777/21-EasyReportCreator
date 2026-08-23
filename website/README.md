# Public site — easyreportcreator.com

PHP 8 product site for STRATO. Light theme only. Page format follows [propertiesmanager.nl](https://www.propertiesmanager.nl/) (nav, hero, mock panel, features, proof, download, footer) with inverted tokens.

## Pages

| File | Purpose |
|---|---|
| `index.php` | Home |
| `product.php` | Features, workflow, requirements |
| `pricing.php` | Version 1 package vs later add-ons |
| `download.php` | How to run the local app |
| `contact.php` | Support |
| `terms.php` | Delivery scope |
| `inc/config.php` | Domain, email, version |
| `assets/styles.css` | Light stylesheet (tokens duplicated from `shared/tokens.css` so STRATO is self-contained) |

## Local preview

```bat
scripts\start-website.bat
```

http://127.0.0.1:8080/

Needs PHP 8 on PATH. On STRATO, upload the contents of this folder to the webspace root for easyreportcreator.com, set PHP 8.x, enable HTTPS.

## STRATO note

This folder is the **public site only**. Do not upload `app/`, `samples/`, or `reference/` to the webspace. The report engine stays on the PC that can see ProcessPower.dcf. See `docs/Architecture.md`.
