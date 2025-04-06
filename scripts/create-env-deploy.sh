#!/bin/bash
set -e

# This script creates the .env.deploy file with environment-specific configuration
# Parameters:
#   $1: Repository name in lowercase (e.g., akibaat/fvn.li)
#   $2: Branch/ref name (e.g., development, production)
#   $3: Whether Dockerfile changed (true/false)

REPO_LOWER="$1"
REF_NAME="$2"
DOCKERFILE_CHANGED="$3"

# Ensure .env.deploy file exists
touch .env.deploy

# Set Docker image tag if Dockerfile changed
if [[ "$DOCKERFILE_CHANGED" == "true" ]]; then
  echo "DOCKER_IMAGE=ghcr.io/${REPO_LOWER}:${REF_NAME}" >> .env.deploy
fi

echo "Created .env.deploy with the following content:"
cat .env.deploy
