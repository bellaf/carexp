#!/usr/bin/env bash

set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

cd "$APP_DIR"

echo "Deploying from $APP_DIR"

git pull --ff-only

composer install --no-dev --optimize-autoloader

if command -v npm >/dev/null 2>&1; then
    npm ci
    npm run build
else
    echo "npm not found, skipping frontend build"
fi

php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

echo "Deploy complete"
