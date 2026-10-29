# MGA System — Infrastructure and Deployment

This document describes the real hosting, Git, API, and mobile setup for this repository. It does not invent a second architecture. The backend already runs on Cloudways. The mobile app is an Expo client for the same Laravel API.

## Existing infrastructure overview

| Item | Current state |
|---|---|
| Product | Medguarda MGA — medical assistance / claims operations |
| Backend host | Cloudways (existing server, already in use) |
| Production URL | `https://system.medguarda.com` |
| Staging URL | `https://mgastaging.medguarda.com` |
| GitHub repository | `Sharlbenjamin/MGA-System` |
| Production branch | `main` |
| Staging / development branch | `staging` |
| Mobile app | Expo SDK 58 app in `/mobile` |
| New Cloudways server | Not required |
| New Cloudways application | Not recommended — staging and production applications already exist |

Verified on 26 October 2026 from this workstation:

- `GET https://system.medguarda.com/api/login` returned JSON `405`
- `POST https://system.medguarda.com/api/login` with invalid credentials returned JSON `422`
- `POST https://mgastaging.medguarda.com/api/login` with invalid credentials returned JSON `422`

Those checks prove the existing Cloudways applications already serve the API over HTTPS. Creating another Cloudways application would add cost and risk overwriting or confusing the live apps.

## Technology stack

| Layer | Technology | Version / notes |
|---|---|---|
| Backend | Laravel | 11.44.1, PHP `^8.3` |
| Admin UI | Filament + Livewire | Filament 3.3, Livewire 3.6 |
| Auth | Laravel Sanctum | 4.3 personal-access tokens |
| Database | MySQL on Cloudways; local default sqlite | 116 Laravel migrations |
| Queue | Database queue | `jobs` / `failed_jobs` |
| Scheduler | Laravel scheduler | IMAP comms every 2–10 minutes, ZIP cleanup daily |
| Web assets | Vite 6 + Tailwind 3 | `npm run build` |
| Mobile | Expo + React Native | Expo 58, RN 0.88, React 19.3 |
| Push | Expo Push API | Tokens in `mobile_push_tokens`; Expo receipts queued in `expo_push_receipts` |
| Payments | Stripe | Invoice payment links |
| Mail / WhatsApp | SMTP + Twilio | WhatsApp send path is currently short-circuited |
| Files | Local `public` disk + Google Drive | Medical case documents |

Required PHP extensions on Cloudways: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `gd`, `intl`, `curl`, `zip`, and **`imap`** for Gmail polling.

## GitHub repository structure

Keep **one repository**. The Expo app is an API client for this backend. Splitting it would duplicate contracts.

```
/
  app/                 Laravel application
  routes/api.php       Sanctum mobile API
  database/migrations  Source of truth for schema
  mobile/              Expo application
  docs/                Operations and API docs
  scripts/             Server and API helpers
  .github/workflows    CI
```

Branching strategy for a small team:

1. Do daily work on `staging`.
2. Open a pull request into `staging` for review when needed.
3. Merge `staging` → `main` only when a release is ready.
4. Cloudways staging application tracks `staging`.
5. Cloudways production application tracks `main`.

Never deploy an unfinished feature branch to production.

## Cloudways application configuration

Reuse the two applications that already exist on the current server.

| Environment | Public URL | Git branch | Database |
|---|---|---|---|
| Staging | `https://mgastaging.medguarda.com` | `staging` | Dedicated staging MySQL database |
| Production | `https://system.medguarda.com` | `main` | Dedicated production MySQL database |

Each Cloudways application must:

1. Use **PHP 8.3** (see `docs/deployment/DEPLOYMENT_GUIDE.md`; older PHP 8.1 mentions are stale).
2. Point the web document root at Laravel `public/`.
3. Keep its own `.env`, `APP_KEY`, and database credentials.
4. Remain a separate application so a staging deploy cannot overwrite production files.

Exact Cloudways application names, system users, and SSH paths are **ACTION REQUIRED** because this workspace cannot open the Cloudways dashboard.

## Database architecture

- Engine: MySQL on the Cloudways server.
- Schema source: existing Laravel migrations. Do not rebuild tables by hand.
- Local development may keep sqlite.
- Staging and production must use **separate databases**.
- Do not copy local or staging data into production automatically.
- Cloudways application backups should stay enabled for both applications.

Safe migration process:

```bash
php artisan migrate:status
# Review pending migrations
php artisan migrate --force    # only after review, on that environment
```

`scripts/cloudways-release.sh` runs `migrate:status` by default and only migrates when you pass `--migrate`.

## Environment structure

| Environment | Purpose | API URL | Debug |
|---|---|---|---|
| Local | Development on your computer | `http://localhost` or your LAN IP | `true` |
| Staging | Online and physical-device testing | `https://mgastaging.medguarda.com` | `true` is acceptable |
| Production | Real customers | `https://system.medguarda.com` | **must be `false`** |

Templates in the repository (no secrets):

- `.env.example` — local
- `.env.staging.example` — staging
- `.env.production.example` — production
- `mobile/.env.example` — Expo public API URL only

Filled `.env` files stay on the server or your machine. They are gitignored.

## Domain and API configuration

Current public endpoints:

- Production API: `https://system.medguarda.com/api`
- Staging API: `https://mgastaging.medguarda.com/api`
- Health (after this work is deployed): `https://<host>/api/health`
- Laravel health page: `https://<host>/up`

HTTPS already works on both hosts. A custom `api.` subdomain is optional, not required.

If a Cloudways application password or WAF starts returning HTML to the mobile app, follow `docs/API_WAF_BYPASS.md`. `/api/*` must always return JSON.

## Deployment procedure

### Staging

1. Push reviewed commits to `origin/staging`.
2. In Cloudways, pull/deploy the staging application from `staging`.
3. SSH into **only** the staging application.
4. Run `bash scripts/cloudways-release.sh`.
5. Review `php artisan migrate:status`.
6. If migrations are pending and reviewed, run `bash scripts/cloudways-release.sh --migrate`.
7. Run `./scripts/verify-api-curl.sh https://mgastaging.medguarda.com`.

### Production

1. Merge `staging` into `main` through GitHub.
2. In Cloudways, pull/deploy the production application from `main`.
3. SSH into **only** the production application.
4. Run `bash scripts/cloudways-release.sh`.
5. Review migrations. Run `--migrate` only when you intend to change the live schema.
6. Run `./scripts/verify-api-curl.sh https://system.medguarda.com`.

Cloudways Git deployment still has to be confirmed in the dashboard. Until that is confirmed, a deploy is a manual Git pull on the correct application.

Do not use `scripts/deployment/deploy-to-live.sh` as the default process. It is an interactive local merge script and is not a Cloudways release. Prefer `docs/deployment/CLOUDWAYS_RELEASE.md`.

## Database migration procedure

1. Confirm you are on the intended application (staging or production).
2. `php artisan migrate:status`
3. Read the pending migration files in `database/migrations`.
4. Take or confirm a Cloudways database backup.
5. `php artisan migrate --force`
6. Smoke-test login and one authenticated list endpoint.

Never run `migrate:fresh`, `migrate:refresh`, or `db:wipe` on staging or production.

## Rollback procedure

1. In Cloudways, redeploy the previous known-good Git commit on that application only.
2. Run `php artisan optimize:clear && php artisan config:cache`.
3. If a migration must be reversed, use `php artisan migrate:rollback --step=1` only after confirming the migration is reversible and a backup exists.
4. If rollback is unsafe, restore the Cloudways database backup taken before the release.

## Backup and recovery instructions

1. Cloudways → the application → **Backup And Restore**.
2. Keep automatic backups enabled on both staging and production.
3. Restore only onto the same application, or onto a new temporary application if you are testing a restore.
4. Do not test restore by overwriting production.

Recovery outline:

1. Enable maintenance on the affected application if users would see partial data.
2. Restore the Cloudways backup.
3. Redeploy the matching Git commit.
4. Verify `/api/health` (once deployed) and `/api/login`.

## Required manual operations

These cannot be finished from the repository alone:

1. Confirm Cloudways application names, PHP 8.3, and `public/` document roots.
2. Confirm Cloudways Git is connected to `Sharlbenjamin/MGA-System` for both applications.
3. Confirm cron: `* * * * * php artisan schedule:run`.
4. Confirm a queue worker or Cloudways Supervisor for `php artisan queue:work`.
5. Confirm `imap` is enabled if communications polling is required.
6. Rotate any mailbox password that was previously stored in git.
7. Create an Expo/EAS project and fill `mobile/app.json` `extra.eas.projectId` before real push notifications.
8. Re-authenticate GitHub CLI (`gh auth login`) if you want GitHub created from this machine.

## Native integration testing requirements

| Feature | In this app? | Local | Physical device | Apple / Google extras |
|---|---|---|---|---|
| Sanctum login | Yes | Simulator OK against staging | Required for SecureStore confidence | None |
| Background fetch / local alerts | Yes | Limited | Required | iOS background fetch |
| Expo remote push | Designed, not live | No | Required | EAS project, FCM, APNs |
| Document picker | Yes | Simulator often OK | Required for real files | None |
| Camera / location / Health / IAP | No | — | — | Not applicable |
| Deep links (`mga://`) | Scheme only | Simulator OK | Required for store claims | Associated domains not configured |

Empty `extra.eas.projectId` means remote Expo push registration returns early. Polling and local notifications still work.

## App Store preparation checklist

- Apple Developer membership
- Bundle ID `com.medguarda.MGA` registered
- Push + background modes reviewed
- Privacy Nutrition labels and privacy policy URL
- EAS production build
- TestFlight before App Review
- Do not claim store readiness until signing, privacy, and push are verified

## Google Play preparation checklist

- Google Play developer account
- Package `com.medguarda.mga`
- FCM / `google-services.json` if remote push is required
- Privacy policy URL
- Internal testing track with a preview APK/AAB
- Production AAB via EAS `production` profile

## Troubleshooting guide

| Symptom | Likely cause | What to do |
|---|---|---|
| Mobile shows “server returned a web page instead of JSON” | WAF, application password, or wrong URL | `docs/API_WAF_BYPASS.md`, then `scripts/verify-api-curl.sh` |
| 422 Invalid credentials | Wrong user/password or wrong environment | Confirm you are on staging vs production |
| 401 after a previously working login | Token revoked or expired after `SANCTUM_TOKEN_EXPIRATION` is set | Sign in again |
| Push never arrives remotely | Empty EAS `projectId` | Create an EAS project; local polling still works |
| Staging deploy changed production | Wrong Cloudways application | Stop, confirm application name, restore if needed |
| Scheduler / inbox stale | Cron or `imap` missing | Enable both on that Cloudways application |
| File uploads fail on Cloudways | `open_basedir` / temp files | Existing Livewire upload workaround in this repo |

## Remaining incomplete tasks

See `docs/DEPLOYMENT_STATUS.md`. The largest remaining items are Cloudways dashboard confirmation, cron/queue verification, credential rotation, EAS project creation, and physical-device testing.

## How to give Cloudways details safely

Send only non-secret values in chat:

- Application names as shown in Cloudways
- Public URLs
- PHP version
- Whether the document root is `public/`
- Whether Git is connected, and which branch
- Whether cron and a queue worker are enabled

Do not paste `.env` contents, database passwords, SSH private keys, or API tokens.
