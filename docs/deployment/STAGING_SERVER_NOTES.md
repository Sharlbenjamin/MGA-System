# Staging server notes (eeyfxyvxmv)

## Deploy path

| Item | Value |
|------|--------|
| SSH user | `master_mgexgxgevm@46.101.27.90` |
| Key | `~/.ssh/cloudways_lockin` (local) |
| Web root | `/home/master/applications/eeyfxyvxmv/public_html` |
| PHP 8.3 CLI | `/usr/bin/php8.3` (same as `applications/eeyfxyvxmv/.cw/bin/php`) |

## Git on Cloudways (admin action)

- **`git_repo`** at `applications/eeyfxyvxmv/git_repo` is owned by **root**; `master_mgexgxgevm` cannot `git fetch` (`Permission denied` on `.git/FETCH_HEAD`).
- **Workaround used:** deploy reviewed commits with `git archive <sha> | ssh … 'cd public_html && tar xf -'`.
- **Do not** `chown -R` from SSH without Cloudways support — fix via panel or ticket.

## Server-only changes (2026-10-29)

| Change | Reason | In Git? |
|--------|--------|---------|
| Removed `NotificationResource/EditNotification.php` and `ListNotifications.php` at wrong path | Duplicate class fatal with `Pages/*` | Repo only has `Pages/` — drift from old deploy |
| Removed `NotificationResource.bak` tree | Same namespaces loaded twice | N/A on clean deploy |
| `bootstrap/app.php` auth 401 fix | Applied via scp then **`2cd6b3e7`** | Yes (`2cd6b3e7`) |
| Crontab line for `eeyfxyvxmv` `schedule:run` | Was missing | Documented below |

## Scheduler (master crontab)

```
* * * * * cd /home/master/applications/cxrtbezstq/public_html/backend && /usr/bin/php8.4 artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/master/applications/eeyfxyvxmv/public_html && /usr/bin/php8.3 artisan schedule:run >> /dev/null 2>&1
```

Cloudways panel equivalent: **Cron → every minute →**  
`cd /home/master/applications/eeyfxyvxmv/public_html && /usr/bin/php8.3 artisan schedule:run >> /dev/null 2>&1`

**Verified 2026-10-29:** `master_mgexgxgevm` **user crontab lines do not execute** on this server (temporary heartbeat file never updated over 2+ minutes). Use **Cloudways → Application → Cron Job Management** for staging, not SSH `crontab -e` alone.

## Migrations applied on staging (mobile push only)

- `2026_10_28_160000_create_mobile_push_tokens_table`
- `2026_10_28_170000_add_notification_sound_to_mobile_push_tokens_table`
- `2026_10_29_120000_create_expo_push_receipts_table`

**26 other migrations remain Pending** — see backlog audit in release checklist / agent report; do not bulk `--force` without review.
