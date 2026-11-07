#!/usr/bin/env bash
# Runs the browser tests from the host. Extra arguments go to Playwright,
# for example: tests/Browser/run.sh member
set -euo pipefail

cd "$(dirname "$0")/../.."

if [ -f public/hot ]; then
    echo 'Stop the Vite dev server first: the tests need the built frontend.' >&2
    exit 1
fi

compose() {
    docker compose --profile browser-tests "$@"
}

./vendor/bin/sail up -d
./vendor/bin/sail pnpm run build

trap 'compose rm --force --stop browser-app' EXIT
compose up --detach --wait --force-recreate browser-app
compose run --rm --no-deps playwright node_modules/.bin/playwright test "$@"
