#!/usr/bin/env bash
# Run this on the Cloudways application after Git has updated the code.
# It never writes a database dump and never runs migrate --force unless you
# pass --migrate after reviewing php artisan migrate:status.

set -euo pipefail

MIGRATE=0
if [[ "${1:-}" == "--migrate" ]]; then
  MIGRATE=1
fi

if [[ ! -f artisan ]]; then
  echo "Run this from the Laravel application root."
  exit 1
fi

if [[ ! -f .env ]]; then
  echo "Missing .env. Copy .env.staging.example or .env.production.example first."
  exit 1
fi

PHP_BIN="${PHP_BIN:-php}"
if [[ -x "../.cw/bin/php" ]]; then
  PHP_BIN="../.cw/bin/php"
elif command -v php8.3 >/dev/null 2>&1; then
  PHP_BIN="php8.3"
fi

composer install --no-dev --optimize-autoloader --no-interaction

if [[ -f package.json ]]; then
  if [[ -f package-lock.json ]]; then
    npm ci --no-audit --no-fund
  else
    echo "No package-lock.json — running npm install (commit a lockfile for reproducible releases)."
    npm install --no-audit --no-fund
  fi
  npm run build
fi

"$PHP_BIN" artisan storage:link || true
"$PHP_BIN" artisan migrate:status
if [[ "$MIGRATE" -eq 1 ]]; then
  "$PHP_BIN" artisan migrate --force
else
  echo "Skipping migrate --force. Re-run with --migrate after reviewing the status above."
fi

"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan filament:cache-components || true
"$PHP_BIN" artisan queue:restart || true

"$PHP_BIN" artisan --version
echo "Release commands finished."
