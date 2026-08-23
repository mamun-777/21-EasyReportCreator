# easyreportcreator.com — DNS checklist (STRATO)

Use this when connecting the domain to the STRATO webspace (scheduled Fri 21 Aug, WP8).

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

## 4. Upload site

1. SFTP or file manager → webspace root for easyreportcreator.com
2. Upload contents of `website/` (not the whole repo)
3. Confirm `index.php` loads and CSS/JS paths work

## 5. Smoke test

- [ ] https://easyreportcreator.com/ loads Home
- [ ] https://www.easyreportcreator.com/ redirects or loads
- [ ] Product, Pricing, Download, Contact, Terms pages open
- [ ] No mixed-content warnings (all assets HTTPS)

## 6. Mail (optional)

Create `support@easyreportcreator.com` in STRATO mail if required for the Contact page.

## Do not upload

- `app/` (Python report engine — runs locally)
- `samples/MN-P-RHN-PID-0001/` (Plant 3D project data)
