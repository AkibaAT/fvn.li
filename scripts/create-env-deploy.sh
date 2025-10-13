#!/bin/bash
set -e

# This script creates the .env.deploy file with environment-specific configuration
# Parameters:
#   $1: Docker tags from meta output (multi-line string)
#   $2: Whether Dockerfile changed (true/false)

DOCKER_TAGS="$1"
DOCKERFILE_CHANGED="$2"

# Create/overwrite .env.deploy file
> .env.deploy

# Set Docker image tag if Dockerfile changed
if [[ "$DOCKERFILE_CHANGED" == "true" ]]; then
    # Extract first tag from multi-line output
    FIRST_TAG=$(echo "$DOCKER_TAGS" | head -n1)
    echo "DOCKER_IMAGE=${FIRST_TAG}" >> .env.deploy
fi

echo "Created .env.deploy with the following content:"
cat .env.deploy
