#!/bin/bash
set -e

# Resolve the Laravel app dir relative to this script, so the deploy works
# regardless of the directory it's invoked from (e.g. Plesk runs it from the
# repo root, which is not the Laravel root).
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "$SCRIPT_DIR/../app" && pwd)"

cd "$APP_DIR"
echo "Deploying Laravel app in: $APP_DIR"

# --- PHP dependencies ---
composer install --no-dev --optimize-autoloader

# --- Frontend assets (Vite) ---
npm ci
npm run build

# --- Database ---
php artisan migrate --force

# --- Rebuild caches from the current code/.env ---
php artisan config:cache
php artisan route:cache
php artisan view:cache

# --- Permissions ---
chmod -R 775 storage bootstrap/cache

echo "Deployment completed"
