#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [ ! -d .git ]; then
  echo "Fehler: Kein Git-Repository im aktuellen Verzeichnis: $PWD"
  exit 1
fi

CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-$CURRENT_BRANCH}"

echo "Deploy-Branch: $DEPLOY_BRANCH"

git fetch origin --prune

git checkout -B "$DEPLOY_BRANCH" "origin/$DEPLOY_BRANCH"
git reset --hard "origin/$DEPLOY_BRANCH"

if [ -f composer.json ]; then
  echo "Composer install (legacy root)"
  composer install --no-dev --optimize-autoloader --no-interaction
fi

if [ -d frontend ]; then
  echo "Frontend build"
  cd frontend
  npm ci
  npm run build
  cd ..
fi

if [ -d laravel ]; then
  echo "Laravel build"
  cd laravel
  composer install --no-dev --optimize-autoloader --no-interaction
  php artisan config:cache
  cd ..
fi

php scripts/ensure-db-baseline.php
php scripts/deploy-check.php

if [ -n "${REMOTE_RESTART_COMMAND:-}" ]; then
  echo "Running restart command: $REMOTE_RESTART_COMMAND"
  eval "$REMOTE_RESTART_COMMAND"
fi

echo "Remote deploy finished."
