#!/bin/bash
set -e

# This script handles deployment of the application
# It can handle both Docker image updates and code-only changes
# All build steps (composer install, npm build) are done in GitHub Actions

RUNTIME_GROUP="${APP_RUNTIME_GROUP:-www-data}"
DEPLOY_UID="$(id -u)"
RUNTIME_GID="$(getent group "${RUNTIME_GROUP}" | cut -d: -f3 || true)"

if [ -z "${RUNTIME_GID}" ]; then
  echo "Runtime group '${RUNTIME_GROUP}' does not exist on this host."
  echo "Create it or set APP_RUNTIME_GROUP to the group shared with the Docker runtime."
  exit 1
fi

APP_EXEC_USER="${APP_EXEC_USER:-${DEPLOY_UID}:${RUNTIME_GID}}"

# New files created during deploy should remain writable by the shared runtime group.
umask 0002

require_deploy_write_access() {
  if [ ! -w . ]; then
    echo "Deployment path is not writable by $(id -un)."
    echo "Expected ownership model: $(id -un):${RUNTIME_GROUP} with group-writable setgid directories."
    echo "One-time repair example:"
    echo "  sudo chown -R $(id -un):${RUNTIME_GROUP} $(pwd)"
    echo "  sudo find $(pwd) -type d -exec chmod 2775 {} +"
    echo "  sudo find $(pwd) -type f -exec chmod g+rw {} +"
    exit 1
  fi
}

ensure_host_write_paths() {
  # The checkout is bind-mounted into /app. Keep code/cache paths owned by the
  # deploy user and writable by the runtime group instead of flipping ownership
  # to www-data during deploy.
  install -d -m 2775 bootstrap/cache public/build
  chgrp -R "${RUNTIME_GROUP}" bootstrap/cache public/build 2>/dev/null || true
  chmod g+rwX . bootstrap bootstrap/cache public public/build scripts || true
  find bootstrap/cache public/build -type d -exec chmod 2775 {} +
  find bootstrap/cache public/build -type f -exec chmod g+rw {} +
}

compose_exec_app() {
  docker compose exec -T --user "${APP_EXEC_USER}" app "$@"
}

compose_exec_root() {
  docker compose exec -T --user root app "$@"
}

ensure_container_runtime_paths() {
  compose_exec_root sh -lc "
    mkdir -p /app/storage/app/public/social-images /app/storage/logs /app/bootstrap/cache /app/public/build
    chgrp -R ${RUNTIME_GROUP} /app/storage /app/bootstrap/cache /app/public/build 2>/dev/null || true
    chmod -R g+rwX /app/storage /app/bootstrap/cache /app/public/build
    find /app/storage /app/bootstrap/cache /app/public/build -type d -exec chmod g+s {} +
  "
}

artisan() {
  compose_exec_app php artisan "$@"
}

bootstrap_denkit_stash_db() {
  docker compose up -d db
  ./scripts/bootstrap-denkit-stash-db.sh
}

read_dotenv_value() {
  local key="$1"
  local fallback="${2:-}"
  local value

  value="$(grep -E "^${key}=" .env 2>/dev/null | tail -n1 | cut -d= -f2- || true)"
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

require_denkit_stash_config() {
  local required_keys=(
    DB_USERNAME
    DB_PASSWORD
    DENKIT_STASH_IMAGE
    DENKIT_STASH_POSTGRES_PASSWORD
    DENKIT_STASH_S3_ACCESS_KEY
    DENKIT_STASH_S3_SECRET_KEY
    DENKIT_API_KEY_HASH_SECRET
  )
  local missing_keys=()
  local key
  local value

  for key in "${required_keys[@]}"; do
    value="${!key:-}"
    if [ -z "${value}" ]; then
      value="$(read_dotenv_value "${key}")"
    fi
    if [ -z "${value}" ]; then
      missing_keys+=("${key}")
    fi
  done

  if [ "${#missing_keys[@]}" -gt 0 ]; then
    echo "Deployment configuration is incomplete; refusing to modify the running stack."
    printf 'Missing required value: %s\n' "${missing_keys[@]}"
    return 1
  fi
}

bootstrap_denkit_stash_user() {
  ./scripts/bootstrap-denkit-stash-user.sh
}

# Load environment variables from .env.deploy
if [ -f .env.deploy ]; then
  echo "Loading deployment configuration from .env.deploy"
  export $(grep -v '^#' .env.deploy | xargs)
fi

require_denkit_stash_config
require_deploy_write_access
ensure_host_write_paths

# Check if DOCKER_IMAGE or DOCKER_IMAGE_SOCIAL_IMAGES is set (indicating a Docker build)
if [ -n "${DOCKER_IMAGE:-}" ] || [ -n "${DOCKER_IMAGE_SOCIAL_IMAGES:-}" ]; then
  if [ -n "${DOCKER_IMAGE:-}" ]; then
    echo "Docker image was updated to ${DOCKER_IMAGE}"
    # Update .env file with the new Docker image
    if grep -q "^DOCKER_IMAGE=" .env; then
      sed -i "s|^DOCKER_IMAGE=.*|DOCKER_IMAGE=${DOCKER_IMAGE}|g" .env
    else
      echo "DOCKER_IMAGE=${DOCKER_IMAGE}" >> .env
    fi
  fi

  if [ -n "${DOCKER_IMAGE_SOCIAL_IMAGES:-}" ]; then
    echo "Social images Docker image was updated to ${DOCKER_IMAGE_SOCIAL_IMAGES}"
    # Update .env file with the new social images Docker image
    if grep -q "^DOCKER_IMAGE_SOCIAL_IMAGES=" .env; then
      sed -i "s|^DOCKER_IMAGE_SOCIAL_IMAGES=.*|DOCKER_IMAGE_SOCIAL_IMAGES=${DOCKER_IMAGE_SOCIAL_IMAGES}|g" .env
    else
      echo "DOCKER_IMAGE_SOCIAL_IMAGES=${DOCKER_IMAGE_SOCIAL_IMAGES}" >> .env
    fi
  fi

  echo "Performing full restart..."

  # Pull the latest images (including tools profile for social-image-generator)
  docker compose --profile tools pull

  # Full restart
  docker compose down --remove-orphans
  bootstrap_denkit_stash_db
  docker compose up -d
  bootstrap_denkit_stash_user

  ensure_container_runtime_paths

  # Run Laravel commands
  artisan storage:link
  artisan config:cache
  artisan cache:forget app.icon-version
  artisan migrate --force
  artisan schedule-monitor:sync
  artisan meilisearch:embedders

  echo "Full restart completed successfully!"
else
  echo "Code-only changes detected, performing hot reload..."

  # Check if container is running
  if docker compose ps app | grep -q "Up"; then
    echo "Container is running, performing cache clear and hot reload..."

    bootstrap_denkit_stash_db
    docker compose up -d denkit-stash
    bootstrap_denkit_stash_user
    ensure_container_runtime_paths

    artisan storage:link

    # Clear Laravel caches
    artisan config:clear
    artisan route:clear
    artisan view:clear
    artisan cache:clear

    # Run migrations
    artisan migrate --force
    artisan schedule-monitor:sync

    artisan meilisearch:embedders

    # Reload FrankenPHP
    compose_exec_root curl -X POST http://localhost:2019/frankenphp/workers/restart

    # Restart workers
    compose_exec_root supervisorctl restart laravel-nightwatch:*
    compose_exec_root supervisorctl restart laravel-queue:*
    compose_exec_root supervisorctl restart inertia-ssr:*

    docker compose restart stats-runner

    echo "FrankenPHP and workers hot reload completed successfully!"
  else
    echo "Container is not running, starting it..."

    # Start the containers without pulling (using existing image)
    bootstrap_denkit_stash_db
    docker compose up -d --remove-orphans
    bootstrap_denkit_stash_user

    ensure_container_runtime_paths

    # Run Laravel commands
    artisan storage:link
    artisan config:cache
    artisan cache:forget app.icon-version
    artisan migrate --force
    artisan schedule-monitor:sync
    artisan meilisearch:embedders

    echo "Container started successfully!"
  fi
fi

# Show running containers
docker compose ps
