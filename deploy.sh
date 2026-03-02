#!/usr/bin/env bash

set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WRITABLE_GROUP="${DEPLOY_WRITABLE_GROUP:-www-data}"

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

mkdir -p storage/app/backups storage/app/backup-temp bootstrap/cache

if command -v sudo >/dev/null 2>&1 && sudo -n true 2>/dev/null; then
    sudo chgrp -R "$WRITABLE_GROUP" storage bootstrap/cache
    sudo chmod -R ug+rwX storage bootstrap/cache
    sudo find storage bootstrap/cache -type d -exec chmod g+s {} \;
else
    echo "Skipping writable path permission reset because passwordless sudo is unavailable."
    echo "Run these manually if scheduled backups or web writes fail:"
    echo "  sudo chgrp -R $WRITABLE_GROUP storage bootstrap/cache"
    echo "  sudo chmod -R ug+rwX storage bootstrap/cache"
    echo "  sudo find storage bootstrap/cache -type d -exec chmod g+s {} \\;"
fi

echo "Deploy complete"
