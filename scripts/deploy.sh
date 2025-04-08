#!/bin/bash
set -e

# This script handles deployment of the application
# It can handle both Docker image updates and code-only changes
# All build steps (composer install, npm build) are done in GitHub Actions

# Load environment variables from .env.deploy
if [ -f .env.deploy ]; then
  echo "Loading deployment configuration from .env.deploy"
  export $(grep -v '^#' .env.deploy | xargs)
fi

# Check if DOCKER_IMAGE is set (indicating a Docker build)
if [ -n "${DOCKER_IMAGE:-}" ]; then
  echo "Docker image was updated to ${DOCKER_IMAGE}, performing full restart..."

  # Update .env file with the new Docker image
  if grep -q "^DOCKER_IMAGE=" .env; then
    sudo sed -i "s|^DOCKER_IMAGE=.*|DOCKER_IMAGE=${DOCKER_IMAGE}|g" .env
  else
    echo "DOCKER_IMAGE=${DOCKER_IMAGE}" >> .env
  fi

  # Pull the latest image
  docker compose pull

  # Full restart
  docker compose down --remove-orphans
  docker compose up -d

  # Run Laravel commands
  docker compose exec app php artisan storage:link
  docker compose exec app php artisan config:cache
  docker compose exec app php artisan migrate --force

  echo "Full restart completed successfully!"
else
  echo "Code-only changes detected, performing hot reload..."

  # Check if container is running
  if docker compose ps app | grep -q "Up"; then
    echo "Container is running, performing cache clear and hot reload..."

    docker compose exec app php artisan storage:link

    # Clear Laravel caches
    docker compose exec app php artisan config:clear
    docker compose exec app php artisan route:clear
    docker compose exec app php artisan view:clear
    docker compose exec app php artisan cache:clear

    # Run migrations
    docker compose exec app php artisan migrate --force

    # Reload FrankenPHP
    docker compose exec app curl -X POST http://localhost:2019/frankenphp/workers/restart

    echo "FrankenPHP hot reload completed successfully!"
  else
    echo "Container is not running, starting it..."

    # Start the containers without pulling (using existing image)
    docker compose up -d --remove-orphans

    # Run Laravel commands
    docker compose exec app php artisan storage:link
    docker compose exec app php artisan config:cache
    docker compose exec app php artisan migrate --force

    echo "Container started successfully!"
  fi
fi

# Show running containers
docker compose ps
