# Project file moves (2026-10-29 audit)

Root keeps Laravel/Node entrypoints only (`artisan`, `composer.*`, `package.json`, `vite.config.js`, `phpunit.xml`, `.env*`, `README.md`, `mobile/`, `app/`, etc.).

## Documentation → `docs/`

| Old path | New path |
|----------|----------|
| `Lets SSH.md` | `docs/deployment/Lets-SSH.md` |
| `DEPLOYMENT_GUIDE.md` | `docs/deployment/DEPLOYMENT_GUIDE.md` |
| `BACKFILL_DRIVE_DOCUMENTS_USAGE.md` | `docs/integrations/` |
| `BILL_GOOGLE_DRIVE_FILTER.md` | `docs/integrations/` |
| `BRANCH_AVAILABILITY_IMPLEMENTATION.md` | `docs/cases/` |
| `DATABASE_NOTIFICATIONS_README.md` | `docs/notifications/` |
| `DISTANCE_CALCULATION.md` | `docs/cases/` |
| `DOCUMENT_ACCESS_DEBUG.md` | `docs/troubleshooting/` |
| `DOCUMENT_PATH_RESOLVER_USAGE.md` | `docs/integrations/` |
| `EMAIL_FUNCTIONALITY_GUIDE.md` | `docs/integrations/` |
| `FILE_DOCUMENT_EXPORT_USAGE.md` | `docs/integrations/` |
| `GOOGLE_DRIVE_DOWNLOADER_USAGE.md` | `docs/integrations/` |
| `INVOICE_EMAIL_SENDING_SUMMARY.md` | `docs/finance/` |
| `NEXT Push.md` (gitignored) | `docs/notifications/NEXT Push.md` |
| `NOTIFICATIONS_README.md` | `docs/notifications/` |
| `NOTIFICATION_SERVICE_GUIDE.md` | `docs/notifications/` |
| `NOTIFICATION_SETUP_SUMMARY.md` | `docs/notifications/` |
| `PATIENT_DUPLICATE_PREVENTION.md` | `docs/cases/` |
| `PATIENT_EDITING_ENHANCEMENTS.md` | `docs/cases/` |
| `SIGNED_DOCUMENT_URLS_USAGE.md` | `docs/integrations/` |
| `SMTP_AUTHENTICATION_ISSUE.md` | `docs/troubleshooting/` |
| `TAXES_EXPORT_ZIP Update Promt.md` | `docs/finance/` |
| `TAXES_WORKFLOW_IMPLEMENTATION_PROMPT.md` | `docs/finance/` |
| `TELEMEDICINE_CONFIRMATION_IMPLEMENTATION.md` | `docs/cases/` |
| `TRX_OUT_DATE_REPAIR.md` | `docs/finance/` |
| `UPLOAD_SCREENSHOT_FEATURE.md` | `docs/cases/` |
| `WIDGET_UPDATE_GUIDE.md` | `docs/WIDGET_UPDATE_GUIDE.md` |
| WhatsApp reference JPEGs (root) | `docs/design/` |

## Scripts → `scripts/`

| Old path | New path |
|----------|----------|
| `deploy-to-live.sh` | `scripts/deployment/deploy-to-live.sh` |
| `apply-livewire-patch.sh` | `scripts/maintenance/` |
| `fix-livewire-server.sh` | `scripts/maintenance/` |
| `fix-livewire-upload.sh` | `scripts/maintenance/` |
| `fix_live_server.php` | `scripts/maintenance/` |
| `generate_all_invoices_clean.php` | `scripts/data-repair/` |
| `test_deployment.php` | `scripts/diagnostics/` |
| `test_file_144.php` | `scripts/diagnostics/` |
| `test_limerick_branches.php` | `scripts/diagnostics/` |

## SQL / data

| Old path | New path |
|----------|----------|
| `fix_contacts_table.sql` | `database/scripts/repair/` |
| `fix_provider_branches_comprehensive.sql` | `database/scripts/repair/` |
| `fix_provider_branches_live.sql` | `database/scripts/repair/` |
| `test_file_144.sql` | `database/scripts/repair/` |
| `cities.yaml`, `contacts.yaml`, `operation.yaml`, `provider.yaml`, `schema.yaml` | `docs/data/yaml/` |
| `user_signature.yaml` | `docs/data/yaml/` |
| `Price List March 2026.xlsx`, `TRX Out*.xlsx` | `docs/data/spreadsheets/` |

## Unchanged / notes

| Path | Note |
|------|------|
| `main` | Zero-byte file at repo root; purpose unknown — **not deleted** |
| `.blueprint` | Laravel Blueprint schema tool config — stays at root |
| `scripts/cloudways-release.sh` | Already under `scripts/` |

**Rollback:** `git mv` (or `mv` for untracked) back to original paths; update bookmarks to `docs/deployment/Lets-SSH.md` and `docs/deployment/CLOUDWAYS_RELEASE.md`.

**Do not run** files under `scripts/data-repair/` or `database/scripts/repair/` without an explicit ops plan.
