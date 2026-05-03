#!/usr/bin/env bash

set -euo pipefail

PORT="${PEST_BROWSER_APP_PORT:-8089}"
HOST_URL="http://127.0.0.1:${PORT}"
export PEST_BROWSER_BASE_URL="${PEST_BROWSER_BASE_URL:-http://web:${PORT}}"
export PEST_BROWSER_PLAYWRIGHT_HOST="${PEST_BROWSER_PLAYWRIGHT_HOST:-playwright}"
export PEST_BROWSER_PLAYWRIGHT_PORT="${PEST_BROWSER_PLAYWRIGHT_PORT:-3000}"

export APP_ENV=testing
export DB_DATABASE="${DB_DATABASE:-db_test}"
export SCOUT_DRIVER="${SCOUT_DRIVER:-collection}"
export MEILISEARCH_HOST="${MEILISEARCH_HOST:-http://localhost:9999}"
export SESSION_DRIVER="${SESSION_DRIVER:-database}"

SERVER_PID=""
SCREENSHOT_DIR="tests/Browser/Screenshots"
SCREENSHOT_BACKUP=""

cleanup() {
    if [[ -n "${SERVER_PID}" ]] && kill -0 "${SERVER_PID}" >/dev/null 2>&1; then
        kill "${SERVER_PID}" >/dev/null 2>&1 || true
        wait "${SERVER_PID}" >/dev/null 2>&1 || true
    fi

    if [[ -n "${SCREENSHOT_BACKUP}" && -d "${SCREENSHOT_BACKUP}" ]]; then
        rm -rf "${SCREENSHOT_DIR}"
        mkdir -p "$(dirname "${SCREENSHOT_DIR}")"
        cp -a "${SCREENSHOT_BACKUP}" "${SCREENSHOT_DIR}"
        rm -rf "${SCREENSHOT_BACKUP}"
    fi
}

trap cleanup EXIT

if [[ -d "${SCREENSHOT_DIR}" ]]; then
    SCREENSHOT_BACKUP="$(mktemp -d)"
    rmdir "${SCREENSHOT_BACKUP}"
    cp -a "${SCREENSHOT_DIR}" "${SCREENSHOT_BACKUP}"
fi

if [[ "${PEST_BROWSER_SKIP_BUILD:-0}" != "1" ]]; then
    bun run build
fi

php artisan migrate:fresh --env=testing

mkdir -p storage/logs
php artisan serve --env=testing --host=0.0.0.0 --port="${PORT}" > storage/logs/pest-browser-server.log 2>&1 &
SERVER_PID="$!"

for _ in $(seq 1 60); do
    if php -r 'exit(@file_get_contents($argv[1]) === false ? 1 : 0);' "${HOST_URL}/login" >/dev/null 2>&1; then
        break
    fi

    if ! kill -0 "${SERVER_PID}" >/dev/null 2>&1; then
        cat storage/logs/pest-browser-server.log >&2 || true
        exit 1
    fi

    sleep 1
done

if ! php -r 'exit(@file_get_contents($argv[1]) === false ? 1 : 0);' "${HOST_URL}/login" >/dev/null 2>&1; then
    cat storage/logs/pest-browser-server.log >&2 || true
    exit 1
fi

php artisan test --env=testing --testsuite=Browser "$@"
