#!/usr/bin/env bash
# Authenticated staging API checks. Credentials and tokens are never printed.
#
# Usage (local terminal only):
#   ./scripts/staging-authenticated-api-check.sh
#
# Optional env (no password): MGA_STAGING_API_URL

set -euo pipefail

BASE_URL="${MGA_STAGING_API_URL:-https://mgastaging.medguarda.com}"
TOKEN=""
DASH_TMP=""
PUSH_TMP=""

cleanup() {
  if [[ -n "${TOKEN:-}" ]]; then
    curl -sS -o /dev/null -X POST "$BASE_URL/api/logout" \
      -H "Accept: application/json" \
      -H "Authorization: Bearer $TOKEN" \
      -H "Content-Type: application/json" || true
    TOKEN=""
  fi
  rm -f "$DASH_TMP" "$PUSH_TMP" 2>/dev/null || true
  unset MGA_STAGING_PASSWORD 2>/dev/null || true
}
trap cleanup EXIT

if [[ ! -t 0 ]]; then
  echo "Run this script in an interactive terminal (credentials are read with hidden input)."
  echo "Example: cd \"$(dirname "$0")/..\" && ./scripts/staging-authenticated-api-check.sh"
  exit 1
fi

printf "Staging API: %s\n" "$BASE_URL"
read -r -p "Email: " MGA_STAGING_EMAIL
read -r -s -p "Password: " MGA_STAGING_PASSWORD
printf "\n"

if [[ -z "${MGA_STAGING_EMAIL}" || -z "${MGA_STAGING_PASSWORD}" ]]; then
  echo "Email and password are required."
  exit 1
fi

DASH_TMP=$(mktemp)
PUSH_TMP=$(mktemp)

LOGIN_JSON=$(curl -sS -X POST "$BASE_URL/api/login" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d "$(php -r 'echo json_encode(["email"=>getenv("MGA_STAGING_EMAIL"),"password"=>getenv("MGA_STAGING_PASSWORD"),"device_name"=>"staging-api-check"], JSON_THROW_ON_ERROR);' \
    MGA_STAGING_EMAIL="$MGA_STAGING_EMAIL" MGA_STAGING_PASSWORD="$MGA_STAGING_PASSWORD")")

unset MGA_STAGING_PASSWORD

TOKEN=$(printf '%s' "$LOGIN_JSON" | php -r '$j=json_decode(stream_get_contents(STDIN),true); echo $j["token"]??"";')
unset LOGIN_JSON

if [[ -z "$TOKEN" ]]; then
  echo "Login failed (no token). Check email/password and try again."
  exit 1
fi

echo "Login: OK"

DASH_CODE=$(curl -sS -o "$DASH_TMP" -w "%{http_code}" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  "$BASE_URL/api/dashboard?duration=Month")

if [[ "$DASH_CODE" != "200" ]]; then
  echo "Dashboard: HTTP $DASH_CODE (expected 200)"
  php -r '$j=json_decode(@file_get_contents(getenv("F")),true); if(is_array($j)) { echo ($j["message"]??json_encode($j,JSON_PARTIAL_OUTPUT_ON_ERROR))."\n"; }' F="$DASH_TMP" 2>/dev/null | head -3
  exit 1
fi

KPI_COUNT=$(php -r '$j=json_decode(@file_get_contents(getenv("F")),true); echo is_array($j["kpis"]??null)?count($j["kpis"]):0;' F="$DASH_TMP")
echo "Dashboard: HTTP 200 (kpis: $KPI_COUNT)"

PUSH_CODE=$(curl -sS -o "$PUSH_TMP" -w "%{http_code}" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  "$BASE_URL/api/push-token/status")

if [[ "$PUSH_CODE" != "200" ]]; then
  echo "Push status: HTTP $PUSH_CODE (expected 200)"
  php -r '$j=json_decode(@file_get_contents(getenv("F")),true); if(is_array($j)) { echo ($j["message"]??"")."\n"; }' F="$PUSH_TMP" 2>/dev/null | head -3
  exit 1
fi

DEV_COUNT=$(php -r '$j=json_decode(@file_get_contents(getenv("F")),true); echo $j["registered_device_count"]??"?";' F="$PUSH_TMP")
STORAGE=$(php -r '$j=json_decode(@file_get_contents(getenv("F")),true); echo $j["storage"]??"?";' F="$PUSH_TMP")
echo "Push status: HTTP 200 (registered_device_count: $DEV_COUNT, storage: $STORAGE)"

echo "Done. Session token revoked via POST /api/logout."
