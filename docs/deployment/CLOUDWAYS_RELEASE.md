# Cloudways release runbook (MGA)

Use the SSH paths in [Lets-SSH.md](./Lets-SSH.md). Run commands on the **target application** (`public_html` = Laravel root).

## 1. Staging first (recommended)

```bash
ssh master_mgexgxgevm@46.101.27.90
cd applications/eeyfxyvxmv/public_html

git fetch origin
git checkout staging
git pull origin staging

bash scripts/cloudways-release.sh
# Review output of migrate:status, then:
bash scripts/cloudways-release.sh --migrate
```

Verify (replace `TOKEN` with a Sanctum token from staging login):

```bash
curl -sS -H "Authorization: Bearer TOKEN" \
  "https://mgastaging.medguarda.com/api/push-token/status" | jq .
curl -sS -H "Authorization: Bearer TOKEN" \
  "https://mgastaging.medguarda.com/api/dashboard?duration=Month" | jq '.hero,.kpis|length'
```

## 2. Production

```bash
ssh master_mgexgxgevm@46.101.27.90
cd applications/fdcpgwbqxd/public_html

git fetch origin
git checkout main
git pull origin main

bash scripts/cloudways-release.sh
bash scripts/cloudways-release.sh --migrate
```

Post-deploy API checks:

```bash
curl -sS -o /dev/null -w "%{http_code}\n" "https://system.medguarda.com/api/login" -X POST \
  -H "Content-Type: application/json" -d '{"email":"x","password":"y"}'
# Expect 422 (validation), not 404/502

curl -sS -H "Authorization: Bearer TOKEN" \
  "https://system.medguarda.com/api/push-token/status"
curl -sS -H "Authorization: Bearer TOKEN" \
  "https://system.medguarda.com/api/dashboard?duration=Month"
```

## 3. Pending migrations (review before `--migrate`)

As of 2026-10-29 in this repository, mobile/push/dashboard work adds at least:

| Migration | Purpose |
|-----------|---------|
| `2026_10_28_160000_create_mobile_push_tokens_table.php` | Persist Expo device tokens |
| `2026_10_28_170000_add_notification_sound_to_mobile_push_tokens_table.php` | Per-device sound preference |
| `2026_10_29_120000_create_expo_push_receipts_table.php` | Deferred Expo receipt checks |

**Always** run on the server (not only locally):

```bash
../.cw/bin/php artisan migrate:status | tail -30
```

Compare `Pending` rows against the table above. Only then:

```bash
../.cw/bin/php artisan migrate --force
```

(Use Cloudways PHP binary path if `php` is not 8.3.)

## 4. Scheduler and queue (required for receipts + comms)

Laravel scheduler must run **every minute** on Cloudways (Cron):

```bash
* * * * * cd /home/.../applications/.../public_html && ../.cw/bin/php artisan schedule:run >> /dev/null 2>&1
```

Scheduled tasks include:

- `push:process-expo-receipts` (Expo delivery receipts, retries with backoff)
- `communications:sync-headers`, `communications:poll`, `clean:temp-zips`

If you use database queues for other jobs, ensure a **queue worker** is supervised (Cloudways “Queue” tab or supervisor). Push sending itself is synchronous in `OpsAlertService`; receipt processing uses the scheduler, not the HTTP request.

## 5. Logs (notification diagnosis)

On the server:

```bash
grep -E "Ops alert skipped mobile push|Expo push|Ops mobile push" storage/logs/laravel.log | tail -50
```

Interpretation:

- `skipped mobile push — no registered device tokens` → token never saved (app, migration, or login).
- `Expo push ticket error` / `receipt error` with `InvalidCredentials` → fix APNs on EAS.
- `DeviceNotRegistered` → stale token; app should re-register.

## 6. Rollback

```bash
git log -1 --oneline
git checkout <previous-commit>
bash scripts/cloudways-release.sh
# Do not roll back migrations without a DBA plan.
```

See [../PROJECT_FILE_MOVES.md](../PROJECT_FILE_MOVES.md) for documentation path changes.
