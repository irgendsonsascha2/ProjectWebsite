#!/usr/bin/env bash
# PHP-Dev-Server mit Upload-Limits für große Videos (siehe includes/bootstrap.php).
set -euo pipefail
cd "$(dirname "$0")"
echo ""
echo "  Website:  http://127.0.0.1:8080/"
echo "  Upload:   upload_max_filesize=2048M post_max_size=2100M"
echo ""
exec php \
  -d upload_max_filesize=2048M \
  -d post_max_size=2100M \
  -d max_execution_time=600 \
  -d max_input_time=600 \
  -S 127.0.0.1:8080 \
  -t .
