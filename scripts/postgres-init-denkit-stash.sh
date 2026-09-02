#!/usr/bin/env bash
set -euo pipefail

: "${DENKIT_STASH_POSTGRES_PASSWORD:?DENKIT_STASH_POSTGRES_PASSWORD must be set}"

denkit_database="${DENKIT_STASH_POSTGRES_DATABASE:-butler}"
denkit_username="${DENKIT_STASH_POSTGRES_USERNAME:-denkit_stash}"

psql --username "${POSTGRES_USER}" --dbname postgres --set=ON_ERROR_STOP=1 \
  --set=denkit_database="${denkit_database}" \
  --set=denkit_username="${denkit_username}" <<'SQL'
\getenv denkit_password DENKIT_STASH_POSTGRES_PASSWORD

SELECT format('CREATE ROLE %I LOGIN PASSWORD %L', :'denkit_username', :'denkit_password')
WHERE NOT EXISTS (
    SELECT 1 FROM pg_catalog.pg_roles WHERE rolname = :'denkit_username'
)
\gexec

SELECT format('CREATE DATABASE %I OWNER %I', :'denkit_database', :'denkit_username')
WHERE NOT EXISTS (
    SELECT 1 FROM pg_catalog.pg_database WHERE datname = :'denkit_database'
)
\gexec
SQL
