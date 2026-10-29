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

composer install --no-dev --optimize-autoloader --no-interaction

if [[ -f package.json ]]; then
  npm ci
  npm run build
fi

php artisan storage:link || true
php artisan migrate:status
if [[ "$MIGRATE" -eq 1 ]]; then
  php artisan migrate --force
else
  echo "Skipping migrate --force. Re-run with --migrate after reviewing the status above."
fi

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:cache-components || true
php artisan queue:restart || true

php artisan --version
echo "Release commands finished."
