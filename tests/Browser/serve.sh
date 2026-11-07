#!/usr/bin/env bash
# Runs a separate copy of the app for the browser tests. It resets the "testing"
# database and keeps files, sessions and emails away from the development app.
set -euo pipefail

cd "$(dirname "$0")/../.."

export APP_ENV=testing
export APP_DEBUG=false
export APP_URL=http://localhost:8001
export BCRYPT_ROUNDS=4
export CACHE_STORE=array
export DB_DATABASE=testing
export DB_URL=
export LARAVEL_STORAGE_PATH=/tmp/ppr-browser-tests/storage
export MAIL_MAILER=array
export PHP_CLI_SERVER_WORKERS=4
export QUEUE_CONNECTION=sync
export SESSION_DRIVER=file

rm -rf "$LARAVEL_STORAGE_PATH"
mkdir -p "$LARAVEL_STORAGE_PATH"/{app/private,framework/cache,framework/sessions,framework/views,logs}

php artisan app:prepare-browser-tests

# variables_order makes LARAVEL_STORAGE_PATH visible to Laravel during web requests.
cd public
exec php -d variables_order=EGPCS -S 0.0.0.0:8001 \
    ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
