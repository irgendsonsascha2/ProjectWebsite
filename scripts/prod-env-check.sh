#!/usr/bin/env bash
# Produktionsnaher Env-Check ohne .env.local dauerhaft zu überschreiben.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ -f .env.local ]]; then
  set -a
  # shellcheck disable=SC1091
  source .env.local
  set +a
fi

if [[ -f .env.production.local ]]; then
  set -a
  # shellcheck disable=SC1091
  source .env.production.local
  set +a
fi

export APP_ENV=production
export APP_ALLOW_DEV_DB_DEFAULTS=0

echo "=== deploy-check (production overlay) ==="
php scripts/deploy-check.php
EXIT=$?
echo ""
echo "Hinweis: APP_ENV/APP_ALLOW_DEV_DB_DEFAULTS nur in dieser Shell gesetzt."
exit "$EXIT"
