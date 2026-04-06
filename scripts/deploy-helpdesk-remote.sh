#!/usr/bin/env bash

set -euo pipefail

BRANCH="${1:-main}"

git fetch origin "$BRANCH"
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

php ./scripts/check-php-platform.php
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan queue:restart || true
php artisan helpdesk:ops-status
