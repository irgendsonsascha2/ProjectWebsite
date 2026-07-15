#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [ ! -d .git ]; then
  echo "Fehler: Kein Git-Repository im aktuellen Verzeichnis: $PWD"
  exit 1
fi

: "${DEPLOY_SHA:?DEPLOY_SHA must contain the commit validated by CI}"

if [[ ! "$DEPLOY_SHA" =~ ^[0-9a-f]{40}$ ]]; then
  echo "Error: DEPLOY_SHA is not a full Git commit SHA."
  exit 1
fi

echo "Deploy commit: $DEPLOY_SHA"

git fetch origin --prune
git cat-file -e "${DEPLOY_SHA}^{commit}"
git checkout --detach "$DEPLOY_SHA"

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

if [ -n "${PHP_FPM_SERVICE:-}" ]; then
  if [[ ! "$PHP_FPM_SERVICE" =~ ^php[0-9]+\.[0-9]+-fpm$ ]]; then
    echo "Error: PHP_FPM_SERVICE must look like php8.4-fpm."
    exit 1
  fi
  sudo systemctl reload "$PHP_FPM_SERVICE"
fi

echo "Remote deploy finished."
