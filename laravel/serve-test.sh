#!/usr/bin/env bash
# Lokaler Testserver für das Testprojekt (nur PHP — ohne Vite-Watch).
set -euo pipefail
cd "$(dirname "$0")"
php artisan config:clear --ansi
echo ""
echo "  Laravel:  http://127.0.0.1:8000"
echo "  Login:    http://127.0.0.1:8000/login"
echo "  Register: http://127.0.0.1:8000/register"
echo ""
exec php artisan serve --host=127.0.0.1
