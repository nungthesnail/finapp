#!/usr/bin/env bash
set -euo pipefail

echo "[api-test] PHP lint"
find apps/api -type f -name "*.php" -print0 | xargs -0 -n1 php -l >/dev/null
echo "[api-test] OK"

