# Mobile remote push (Expo / APNs)

## Flow

1. Business event (`File` status → Assisted, etc.) calls `OpsAlertService`.
2. Filament database notification + Expo push to all `mobile_push_tokens` for permitted users.
3. Device must register via `POST /api/push-token` after login (Profile → Re-register).

## Diagnostics

- App: Profile → **Remote notifications** (local + server status, no token values).
- API: `GET /api/push-token/status` (authenticated).
- Server logs: `Ops alert skipped mobile push`, `Expo push ticket error`, `Expo push receipt error`.

## Manual requirements (not fixable in PHP/TS alone)

1. Run migrations: `mobile_push_tokens` (+ `notification_sound` column).
2. Deploy backend with `OpsAlertService` Expo **ticket** send during the request; **receipts** are checked asynchronously via `push:process-expo-receipts` (scheduler every minute).
3. EAS: Apple Push Notifications key on project `@sharlhany/mga` (`27a8000f-3bdf-41ac-a0d4-fa35239ea394`).
4. Physical device with production/TestFlight build; Expo Go does not match production push credentials.
5. Test with a **second user** changing a case (actor excluded from DB alert; push still targets all tokens).

## Platform limits

iOS may delay or coalesce notifications; Do Not Disturb and Focus affect delivery. Background fetch is **not** used for alert delivery when push is registered.
