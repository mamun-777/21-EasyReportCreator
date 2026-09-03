# easyreportcreator.com — DNS checklist (STRATO)

Use this when connecting the domain to the STRATO webspace. Full deploy steps: **`STRATO-Deploy.md`**.

## 1. STRATO panel

1. Log in at [https://www.strato.nl/](https://www.strato.nl/)
2. Open **Domains** → **easyreportcreator.com**
3. Point the domain to your hosting package (webspace / “Webhosting”)

## 2. DNS records

| Host | Type | Target |
|---|---|---|
| `@` | A or STRATO default | STRATO webspace IP (shown in panel) |
| `www` | CNAME or A | Same webspace |

If the domain is registered elsewhere, set the nameservers to STRATO’s (listed in the domain overview).

## 3. HTTPS

1. **Domains** → **easyreportcreator.com** → **SSL**
2. Enable **SSL certificate** (Let’s Encrypt or included cert)
3. Wait for activation (usually minutes to a few hours)

## 4. Upload site + report app

1. SFTP or file manager → webspace root for easyreportcreator.com
2. Upload contents of `website/` (marketing pages **and** `report/`, `inc/plant3d/`, `report_templates/`)
3. Ensure `data/uploads/` is writable
4. Confirm `index.php` and `/report/` load; CSS/JS paths work

## 5. Smoke test

- [ ] https://easyreportcreator.com/ loads Home
- [ ] https://www.easyreportcreator.com/ redirects or loads
- [ ] https://easyreportcreator.com/report/ opens the report app
- [ ] Upload sample `ProcessPower.dcf` → valve list shows **344** rows
- [ ] Export Excel downloads
- [ ] No mixed-content warnings (all assets HTTPS)

## 6. Mail (optional)

Create `support@easyreportcreator.com` in STRATO mail if required for the Contact page.

## Do not upload

- `app/` (Python — local tests only)
- `samples/MN-P-RHN-PID-0001/` (keep fixture local; upload only the `.dcf` via the app when testing)
