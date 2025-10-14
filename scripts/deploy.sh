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

# Check if DOCKER_IMAGE or DOCKER_IMAGE_SOCIAL_IMAGES is set (indicating a Docker build)
if [ -n "${DOCKER_IMAGE:-}" ] || [ -n "${DOCKER_IMAGE_SOCIAL_IMAGES:-}" ]; then
  if [ -n "${DOCKER_IMAGE:-}" ]; then
    echo "Docker image was updated to ${DOCKER_IMAGE}"
    # Update .env file with the new Docker image
    if grep -q "^DOCKER_IMAGE=" .env; then
      sudo sed -i "s|^DOCKER_IMAGE=.*|DOCKER_IMAGE=${DOCKER_IMAGE}|g" .env
    else
      echo "DOCKER_IMAGE=${DOCKER_IMAGE}" >> .env
    fi
  fi

  if [ -n "${DOCKER_IMAGE_SOCIAL_IMAGES:-}" ]; then
    echo "Social images Docker image was updated to ${DOCKER_IMAGE_SOCIAL_IMAGES}"
    # Update .env file with the new social images Docker image
    if grep -q "^DOCKER_IMAGE_SOCIAL_IMAGES=" .env; then
      sudo sed -i "s|^DOCKER_IMAGE_SOCIAL_IMAGES=.*|DOCKER_IMAGE_SOCIAL_IMAGES=${DOCKER_IMAGE_SOCIAL_IMAGES}|g" .env
    else
      echo "DOCKER_IMAGE_SOCIAL_IMAGES=${DOCKER_IMAGE_SOCIAL_IMAGES}" >> .env
    fi
  fi

  echo "Performing full restart..."

  # Pull the latest images
  docker compose pull

  # Full restart
  docker compose down --remove-orphans
  docker compose up -d

  # Ensure social images directory exists with proper permissions
  docker compose exec app mkdir -p /app/storage/app/public/social-images
  docker compose exec app chown -R www-data:www-data /app/storage/app/public/social-images

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

    # Restart workers
    docker compose exec app supervisorctl restart laravel-nightwatch:*
    docker compose exec app supervisorctl restart laravel-queue:*
    docker compose exec app supervisorctl restart inertia-ssr:*

    echo "FrankenPHP and workers hot reload completed successfully!"
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
