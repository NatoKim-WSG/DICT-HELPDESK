#!/usr/bin/env bash

set -euo pipefail

BRANCH="${1:-main}"
EXPECTED_PATH="/opt/helpdesk"

if [[ "$(pwd -P)" != "$EXPECTED_PATH" ]]; then
    echo "This deploy script is restricted to $EXPECTED_PATH." >&2
    exit 1
fi

if [[ ! -f artisan || ! -f composer.json ]]; then
    echo "Expected a Laravel app checkout in $EXPECTED_PATH." >&2
    exit 1
fi

git fetch origin "$BRANCH"
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

php ./scripts/check-php-platform.php
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
# The live app serves the generated assets from public/build, so keep the
# deploy host tidy by removing the transient frontend toolchain afterward.
rm -rf node_modules
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan queue:restart || true
php artisan helpdesk:ops-status
