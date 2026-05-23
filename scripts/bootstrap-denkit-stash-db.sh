#!/bin/bash
set -euo pipefail

if [ ! -f .env ]; then
  echo ".env is required before bootstrapping the Denkit stash database."
  exit 1
fi

read_env_value() {
  local key="$1"
  local fallback="${2:-}"
  local value

  value="$(grep -E "^${key}=" .env | tail -n1 | cut -d= -f2- || true)"
  if [ -z "${value}" ]; then
    printf '%s' "${fallback}"
    return
  fi

  value="${value%\"}"
  value="${value#\"}"
  value="${value%\'}"
  value="${value#\'}"
  printf '%s' "${value}"
}

DB_USERNAME="$(read_env_value DB_USERNAME)"
DB_PASSWORD="$(read_env_value DB_PASSWORD)"
DENKIT_STASH_POSTGRES_USERNAME="$(read_env_value DENKIT_STASH_POSTGRES_USERNAME denkit_stash)"
DENKIT_STASH_POSTGRES_PASSWORD="$(read_env_value DENKIT_STASH_POSTGRES_PASSWORD)"
DENKIT_STASH_POSTGRES_DATABASE="$(read_env_value DENKIT_STASH_POSTGRES_DATABASE butler)"

if [ -z "${DB_USERNAME}" ] || [ -z "${DB_PASSWORD}" ]; then
  echo "DB_USERNAME and DB_PASSWORD must be set in .env."
  exit 1
fi

if [ -z "${DENKIT_STASH_POSTGRES_PASSWORD}" ]; then
  echo "DENKIT_STASH_POSTGRES_PASSWORD must be set in .env."
  exit 1
fi

echo "Bootstrapping Denkit stash database '${DENKIT_STASH_POSTGRES_DATABASE}' for role '${DENKIT_STASH_POSTGRES_USERNAME}'..."

docker compose exec -T \
  -e PGUSER="${DB_USERNAME}" \
  -e PGPASSWORD="${DB_PASSWORD}" \
  -e DENKIT_STASH_POSTGRES_USERNAME="${DENKIT_STASH_POSTGRES_USERNAME}" \
  -e DENKIT_STASH_POSTGRES_PASSWORD="${DENKIT_STASH_POSTGRES_PASSWORD}" \
  -e DENKIT_STASH_POSTGRES_DATABASE="${DENKIT_STASH_POSTGRES_DATABASE}" \
  db sh -lc 'psql -v ON_ERROR_STOP=1 \
    -v denkit_user="$DENKIT_STASH_POSTGRES_USERNAME" \
    -v denkit_password="$DENKIT_STASH_POSTGRES_PASSWORD" \
    -v denkit_db="$DENKIT_STASH_POSTGRES_DATABASE" \
    -d postgres <<'"'"'SQL'"'"'
SELECT format('"'"'CREATE ROLE %I LOGIN PASSWORD %L'"'"', :'"'"'denkit_user'"'"', :'"'"'denkit_password'"'"')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'"'"'denkit_user'"'"')\gexec

SELECT format('"'"'ALTER ROLE %I WITH PASSWORD %L'"'"', :'"'"'denkit_user'"'"', :'"'"'denkit_password'"'"')\gexec

SELECT format('"'"'CREATE DATABASE %I OWNER %I'"'"', :'"'"'denkit_db'"'"', :'"'"'denkit_user'"'"')
WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = :'"'"'denkit_db'"'"')\gexec

SELECT format('"'"'ALTER DATABASE %I OWNER TO %I'"'"', :'"'"'denkit_db'"'"', :'"'"'denkit_user'"'"')\gexec
SQL'

echo "Denkit stash database bootstrap completed."
