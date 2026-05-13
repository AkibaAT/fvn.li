#!/usr/bin/env bash

set -euo pipefail

export PGPASSWORD="${POSTGRES_PASSWORD:-db}"

if ! psql \
  -h "${POSTGRES_HOST:-db}" \
  -p "${POSTGRES_PORT:-5432}" \
  -U "${POSTGRES_USER:-db}" \
  -d postgres \
  -tc "SELECT 1 FROM pg_database WHERE datname = '${POSTGRES_DB:-butler}'" | grep -q 1; then
  createdb \
    -h "${POSTGRES_HOST:-db}" \
    -p "${POSTGRES_PORT:-5432}" \
    -U "${POSTGRES_USER:-db}" \
    "${POSTGRES_DB:-butler}"
fi

go build -buildvcs=false -o /tmp/fvn-butler-server .

exec /tmp/fvn-butler-server --port=8081
