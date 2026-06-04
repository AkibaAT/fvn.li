#!/usr/bin/env bash

set -euo pipefail

HOT_FILE="public/hot"
HOT_BACKUP=""

cleanup() {
    if [[ -n "${HOT_BACKUP}" && -f "${HOT_BACKUP}" ]]; then
        mv "${HOT_BACKUP}" "${HOT_FILE}"
    fi
}

trap cleanup EXIT

if [[ -f "${HOT_FILE}" ]]; then
    HOT_BACKUP="$(mktemp)"
    mv "${HOT_FILE}" "${HOT_BACKUP}"
fi

export SESSION_DRIVER="${SESSION_DRIVER:-database}"
export E2E_START_LARAVEL_SERVER="${E2E_START_LARAVEL_SERVER:-1}"
export E2E_BASE_URL="${E2E_BASE_URL:-http://web:8088}"
export PW_TEST_CONNECT_WS_ENDPOINT="${PW_TEST_CONNECT_WS_ENDPOINT:-ws://playwright:3000/}"

./node_modules/.bin/playwright "$@"
