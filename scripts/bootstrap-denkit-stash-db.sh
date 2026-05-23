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

  value="${!key:-}"
  if [ -n "${value}" ]; then
    printf '%s' "${value}"
    return
  fi

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

payload_dir="$(mktemp -d)"
cleanup_payload() {
  rm -rf "${payload_dir}"
}
trap cleanup_payload EXIT
chmod 700 "${payload_dir}"

printf '%s' "${DB_USERNAME}" > "${payload_dir}/db_username"
printf '%s' "${DB_PASSWORD}" > "${payload_dir}/db_password"
printf '%s' "${DENKIT_STASH_POSTGRES_USERNAME}" > "${payload_dir}/denkit_username"
printf '%s' "${DENKIT_STASH_POSTGRES_PASSWORD}" > "${payload_dir}/denkit_password"
printf '%s' "${DENKIT_STASH_POSTGRES_DATABASE}" > "${payload_dir}/denkit_database"
chmod 600 "${payload_dir}/"*

cat > "${payload_dir}/bootstrap.sh" <<'CONTAINER_SCRIPT'
set -eu

payload_dir="$(dirname "$0")"

DB_USERNAME="$(cat "${payload_dir}/db_username")"
DB_PASSWORD="$(cat "${payload_dir}/db_password")"
DENKIT_STASH_POSTGRES_USERNAME="$(cat "${payload_dir}/denkit_username")"
DENKIT_STASH_POSTGRES_PASSWORD="$(cat "${payload_dir}/denkit_password")"
DENKIT_STASH_POSTGRES_DATABASE="$(cat "${payload_dir}/denkit_database")"

sql_literal() {
  printf "%s" "$1" | sed "s/'/''/g"
}

pgpass_file="${payload_dir}/.pgpass"
printf '*:*:*:%s:%s\n' "${DB_USERNAME}" "${DB_PASSWORD}" > "${pgpass_file}"
chmod 600 "${pgpass_file}"
export PGPASSFILE="${pgpass_file}"

denkit_user="$(sql_literal "${DENKIT_STASH_POSTGRES_USERNAME}")"
denkit_password="$(sql_literal "${DENKIT_STASH_POSTGRES_PASSWORD}")"
denkit_db="$(sql_literal "${DENKIT_STASH_POSTGRES_DATABASE}")"

psql -v ON_ERROR_STOP=1 -U "${DB_USERNAME}" -d postgres <<SQL
SELECT format('CREATE ROLE %I LOGIN PASSWORD %L', '${denkit_user}', '${denkit_password}')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '${denkit_user}')\gexec

SELECT format('ALTER ROLE %I WITH PASSWORD %L', '${denkit_user}', '${denkit_password}')\gexec

SELECT format('CREATE DATABASE %I OWNER %I', '${denkit_db}', '${denkit_user}')
WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = '${denkit_db}')\gexec

SELECT format('ALTER DATABASE %I OWNER TO %I', '${denkit_db}', '${denkit_user}')\gexec
SQL
CONTAINER_SCRIPT

chmod 700 "${payload_dir}/bootstrap.sh"

tar -C "${payload_dir}" -cf - . | docker compose exec -T db sh -c '
  set -eu
  tmp_dir="$(mktemp -d)"
  cleanup() {
    rm -rf "${tmp_dir}"
  }
  trap cleanup EXIT
  tar -xf - -C "${tmp_dir}"
  sh "${tmp_dir}/bootstrap.sh"
'

echo "Denkit stash database bootstrap completed."
