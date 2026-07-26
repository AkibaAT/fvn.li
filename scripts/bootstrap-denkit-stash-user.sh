#!/bin/bash
set -euo pipefail

if [ ! -f .env ]; then
  echo ".env is required before bootstrapping the Denkit stash user."
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

DENKIT_STASH_USERNAME="$(read_env_value DENKIT_STASH_USERNAME fvn-li)"
DENKIT_STASH_API_KEY="$(read_env_value DENKIT_STASH_API_KEY)"

if [ -z "${DENKIT_STASH_API_KEY}" ]; then
  echo "DENKIT_STASH_API_KEY is not set; skipping DenKit Stash API user bootstrap."
  exit 0
fi

echo "Ensuring DenKit Stash admin user '${DENKIT_STASH_USERNAME}'..."

docker compose exec -T denkit-stash ./denkit-stash \
  --ensure-admin="${DENKIT_STASH_USERNAME}" \
  --api-key="${DENKIT_STASH_API_KEY}"

echo "DenKit Stash user bootstrap completed."
