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

if [[ -n "$(git status --porcelain)" ]]; then
    echo "Refusing to deploy from a dirty working tree in $EXPECTED_PATH." >&2
    echo "Commit, stash, or discard local changes on the server before deploying." >&2
    exit 1
fi

git fetch origin "$BRANCH"
PREVIOUS_HEAD="$(git rev-parse HEAD)"
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

php ./scripts/check-php-platform.php
composer install --no-dev --optimize-autoloader --no-interaction

FRONTEND_CHANGED=0
if [[ ! -f public/build/manifest.json ]]; then
    FRONTEND_CHANGED=1
elif ! git diff --quiet "$PREVIOUS_HEAD" HEAD -- \
    package.json \
    package-lock.json \
    vite.config.js \
    tailwind.config.js \
    postcss.config.js \
    eslint.config.js \
    resources/js \
    resources/css; then
    FRONTEND_CHANGED=1
fi

if [[ "$FRONTEND_CHANGED" == "1" ]]; then
    npm ci
    npm run build
    # The live app serves the generated assets from public/build, so keep the
    # deploy host tidy by removing the transient frontend toolchain afterward.
    rm -rf node_modules
else
    echo "Skipping frontend build; no tracked asset inputs changed."
fi
php artisan migrate --force
php artisan optimize:clear
php artisan helpdesk:cleanup-runtime
php artisan optimize
php artisan queue:restart || true

for attempt in 1 2 3 4 5; do
    if php artisan helpdesk:ops-status --fail-on-warning; then
        break
    fi

    if [[ "$attempt" == "5" ]]; then
        exit 1
    fi

    sleep 3
done
