#!/usr/bin/env bash
# Beispiel: tägliches Backup (cron) — Pfade und URI anpassen, nicht committen mit Secrets.
set -euo pipefail

ROOT="/var/www/projectwebsite"
BACKUP_DIR="/var/backups/projectwebsite"
DATE="$(date +%Y%m%d-%H%M)"
MONGODB_URI="${MONGODB_URI:-mongodb://admin:CHANGE_ME@127.0.0.1:27017/portfolio_db?authSource=portfolio_db}"

mkdir -p "${BACKUP_DIR}/${DATE}"
mongodump --uri="${MONGODB_URI}" --out="${BACKUP_DIR}/${DATE}/mongo"
tar -czf "${BACKUP_DIR}/${DATE}/content.tar.gz" -C "${ROOT}" content/images content/videos

# Alte Backups (>14 Tage) optional löschen:
# find "${BACKUP_DIR}" -maxdepth 1 -type d -mtime +14 -exec rm -rf {} +

echo "Backup: ${BACKUP_DIR}/${DATE}"
