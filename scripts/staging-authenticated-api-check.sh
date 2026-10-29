#!/usr/bin/env bash
# Authenticated staging API checks. Credentials never appear in output.
# Usage (local machine):
#   export MGA_STAGING_EMAIL='your@email'
#   export MGA_STAGING_PASSWORD='your-password'
#   ./scripts/staging-authenticated-api-check.sh
#
# Optional: MGA_STAGING_API_URL (default https://mgastaging.medguarda.com)

set -euo pipefail

BASE_URL="${MGA_STAGING_API_URL:-https://mgastaging.medguarda.com}"

if [[ -z "${MGA_STAGING_EMAIL:-}" || -z "${MGA_STAGING_PASSWORD:-}" ]]; then
  echo "Set MGA_STAGING_EMAIL and MGA_STAGING_PASSWORD in your shell (not in chat)."
  exit 1
fi

LOGIN_JSON=$(curl -sS -X POST "$BASE_URL/api/login" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d "$(printf '{"email":"%s","password":"%s","device_name":"staging-api-check"}' "$MGA_STAGING_EMAIL" "$MGA_STAGING_PASSWORD")")

TOKEN=$(printf '%s' "$LOGIN_JSON" | php -r '$j=json_decode(stream_get_contents(STDIN),true); echo $j["token"]??"";')

if [[ -z "$TOKEN" ]]; then
  echo "Login failed (no token in response). HTTP body omitted for safety."
  exit 1
fi

echo "Login: OK (token received)"

DASH_CODE=$(curl -sS -o /tmp/mga_dash.json -w "%{http_code}" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  "$BASE_URL/api/dashboard?duration=Month")
KPI_COUNT=$(php -r '$j=json_decode(@file_get_contents("/tmp/mga_dash.json"),true); echo is_array($j["kpis"]??null)?count($j["kpis"]):0;')
echo "Dashboard: HTTP $DASH_CODE (kpis count: $KPI_COUNT)"

PUSH_CODE=$(curl -sS -o /tmp/mga_push.json -w "%{http_code}" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  "$BASE_URL/api/push-token/status")
DEV_COUNT=$(php -r '$j=json_decode(@file_get_contents("/tmp/mga_push.json"),true); echo $j["registered_device_count"]??"?";')
STORAGE=$(php -r '$j=json_decode(@file_get_contents("/tmp/mga_push.json"),true); echo $j["storage"]??"?";')
echo "Push status: HTTP $PUSH_CODE (registered_device_count: $DEV_COUNT, storage: $STORAGE)"

rm -f /tmp/mga_dash.json /tmp/mga_push.json
