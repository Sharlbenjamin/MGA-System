# MGA release checklist (2026-10-29)

Track phases: **Not started** | **In progress** | **Blocked** | **Verified**

## Phase 1 — Readiness (local)

| Item | Status |
|------|--------|
| Backend unit/smoke tests | Run `php artisan test --filter=ApiInfrastructureSmokeTest` + unit tests |
| Mobile typecheck | `cd mobile && npx tsc --noEmit` |
| Migrations reviewed (non-destructive) | `mobile_push_tokens`, `notification_sound`, `expo_push_receipts` — **create-only** |
| Git: commit release bundle on `staging` | Pending your approval to commit/push |
| Rollback | `git checkout <prev>` + `scripts/cloudways-release.sh` (migrations not auto-reverted) |

### Files in this release (high level)

- **API:** `routes/api.php`, `DashboardApiController`, `NotificationApiController`, mobile API controllers
- **Push:** `OpsAlertService`, `MobilePushTokenStore`, `ExpoPushReceiptService`, `ProcessExpoPushReceiptsCommand`, `routes/console.php`
- **Dashboard:** `MobileDashboardService`
- **Mobile:** `mobile/` (Home tab, dashboard UI, alerts, theme)
- **Docs/scripts:** `docs/deployment/*`, `scripts/cloudways-release.sh`, file moves under `docs/`

## Phase 2 — Staging backend

| Step | Status |
|------|--------|
| Cloudways staging backup | Manual |
| SSH → `applications/eeyfxyvxmv/public_html` | Manual |
| `git pull origin staging` | Manual |
| `bash scripts/cloudways-release.sh` → review `migrate:status` | Manual |
| `bash scripts/cloudways-release.sh --migrate` | Manual |
| Cron: `schedule:run` every minute | Verify in Cloudways |
| `/api/health` 200 | Verify |
| `/api/dashboard` + `/api/push-token/status` authed 200 | Verify |
| Web login + one case view | Manual |

## Phase 3 — Staging mobile + push

| Step | Status |
|------|--------|
| Install build (preview profile → staging API) or dev client | Manual |
| Push register + second-user Assisted test | Manual |
| Closed-app delivery | **Verified only on device** |

## Phase 4 — Production backend

After Phase 3 verified + your approval.

## Phase 5 — TestFlight (production API)

| Step | Status |
|------|--------|
| `eas build --profile production` | After approval |
| Submit TestFlight (not App Store review) | Manual |
| Production push + dashboard QA | Device |

## Native rebuild required?

- **Yes:** `react-native-svg`, Home tab, notification client, `expo-notifications` sounds (already in native build from prior profiles)
- **EAS Update:** Not configured — JS changes ship via new build
- **Staging testing:** Use EAS **`preview`** profile (`EXPO_PUBLIC_API_URL=mgastaging`) before production TestFlight
