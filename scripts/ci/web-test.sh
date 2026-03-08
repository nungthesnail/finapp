#!/usr/bin/env bash
set -euo pipefail

echo "[web-test] Static smoke checks"
test -f apps/web/public/index.html
grep -q "<title>FinWiseAi</title>" apps/web/public/index.html
echo "[web-test] OK"

