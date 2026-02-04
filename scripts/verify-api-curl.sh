#!/usr/bin/env bash
# Verify that /api/* returns JSON (not HTML/307). Run from server or local.
# Usage: ./scripts/verify-api-curl.sh [BASE_URL]
# Example: ./scripts/verify-api-curl.sh https://mgastaging.medguarda.com

set -e
BASE_URL="${1:-https://mgastaging.medguarda.com}"
RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m'

echo "=== API verification for $BASE_URL ==="
echo ""

# 1) POST /api/login (expect 200 JSON or 422 JSON, never HTML/307)
echo "1) POST /api/login"
RES=$(curl -s -o /tmp/api_login_body.txt -w "%{http_code}|%{redirect_url}" -X POST "$BASE_URL/api/login" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"wrong","device_name":"ios"}' 2>/dev/null || true)
HTTP_CODE="${RES%%|*}"
REDIRECT="${RES#*|}"
BODY=$(cat /tmp/api_login_body.txt 2>/dev/null || true)

if [[ "$HTTP_CODE" == "307" ]] || [[ "$HTTP_CODE" == "302" ]] || [[ "$REDIRECT" != "" ]]; then
  echo -e "${RED}FAIL: Got redirect ($HTTP_CODE) or redirect_url=$REDIRECT${NC}"
  echo "Body (first 200 chars): ${BODY:0:200}"
  exit 1
fi
if echo "$BODY" | grep -qi "javascript is required\|you are being redirected\|<html"; then
  echo -e "${RED}FAIL: Response is HTML/challenge, not JSON${NC}"
  echo "Body (first 300 chars): ${BODY:0:300}"
  exit 1
fi
if [[ "$HTTP_CODE" == "422" ]]; then
  echo -e "${GREEN}OK: 422 JSON (invalid credentials)${NC}"
elif [[ "$HTTP_CODE" == "200" ]]; then
  echo -e "${GREEN}OK: 200 JSON (login success)${NC}"
else
  echo "HTTP $HTTP_CODE - Body: ${BODY:0:150}"
fi
echo ""

# 2) GET /api/login (expect 405 JSON hint)
echo "2) GET /api/login (expect 405 JSON)"
RES=$(curl -s -o /tmp/api_get_login.txt -w "%{http_code}" "$BASE_URL/api/login" -H "Accept: application/json" 2>/dev/null || true)
BODY=$(cat /tmp/api_get_login.txt 2>/dev/null || true)
if echo "$BODY" | grep -qi "<html\|javascript is required"; then
  echo -e "${RED}FAIL: GET /api/login returned HTML${NC}"
  exit 1
fi
echo -e "${GREEN}OK: GET /api/login returned non-HTML (HTTP $RES)${NC}"
echo ""

echo "=== Verification done. If both steps show OK, /api is not returning HTML/redirects. ==="
echo "For full auth test: use POST /api/login with real credentials, then GET /api/user with Bearer token."
