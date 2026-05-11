#!/bin/bash
set -euo pipefail

DEPLOY_PATH="${1:-$(pwd)}"
DEPLOY_USER="${DEPLOY_USER:-github}"
RUNTIME_GROUP="${APP_RUNTIME_GROUP:-www-data}"

if [ "$(id -u)" -ne 0 ]; then
  echo "Run this one-time repair script with sudo:"
  echo "  sudo $0 ${DEPLOY_PATH}"
  exit 1
fi

if ! id "${DEPLOY_USER}" >/dev/null 2>&1; then
  echo "Deploy user '${DEPLOY_USER}' does not exist."
  exit 1
fi

if ! getent group "${RUNTIME_GROUP}" >/dev/null 2>&1; then
  echo "Runtime group '${RUNTIME_GROUP}' does not exist."
  exit 1
fi

echo "Adding ${DEPLOY_USER} to ${RUNTIME_GROUP}..."
usermod -aG "${RUNTIME_GROUP}" "${DEPLOY_USER}"

echo "Normalizing ${DEPLOY_PATH} to ${DEPLOY_USER}:${RUNTIME_GROUP}..."
chown -R "${DEPLOY_USER}:${RUNTIME_GROUP}" "${DEPLOY_PATH}"

echo "Applying group-writable setgid directory permissions..."
find "${DEPLOY_PATH}" -type d -exec chmod 2775 {} +
find "${DEPLOY_PATH}" -type f -exec chmod g+rw {} +

echo "Deployment permissions normalized."
echo "The ${DEPLOY_USER} user may need to log out and back in for the new group membership to apply."
