#!/usr/bin/env bash
set -euo pipefail

echo "[e2e] docker compose up"
docker compose up -d

echo "[e2e] waiting for services"
for i in {1..20}; do
  if curl -fsS http://localhost:8080/api/health >/dev/null; then
    break
  fi
  sleep 1
done

curl -fsS http://localhost:8080 >/dev/null
curl -fsS http://localhost:8080/api/health >/dev/null
echo "[e2e] OK"

