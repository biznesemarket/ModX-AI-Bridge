#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "== 1. Static PHP preflight =="
php "${ROOT}/tests/Integration/preflight.php"

echo "== 2. Start MODX test stack =="
docker compose -f "${ROOT}/docker-compose.yml" up -d --build

cleanup() {
  if [ "${KEEP_MODX_STACK:-0}" != "1" ]; then
    docker compose -f "${ROOT}/docker-compose.yml" down -v
  fi
}
trap cleanup EXIT

echo "== 3. Wait for MODX container =="
for i in $(seq 1 60); do
  if docker compose -f "${ROOT}/docker-compose.yml" exec -T modx test -f /var/www/html/config.core.php 2>/dev/null; then
    break
  fi
  sleep 2
done

echo "== 4. Install MODX =="
docker compose -f "${ROOT}/docker-compose.yml" exec -T modx \
  bash -lc 'if [ ! -f /var/www/html/core/config/config.inc.php ]; then /workspace/modx-ai-bridge/scripts/modx-install.sh; fi'

echo "== 5. Install ModX AI Bridge package =="
docker compose -f "${ROOT}/docker-compose.yml" exec -T modx \
  bash -lc 'cd /workspace/modx-ai-bridge && composer install --no-interaction --prefer-dist'

docker compose -f "${ROOT}/docker-compose.yml" exec -T modx \
  bash -lc 'cd /workspace/modx-ai-bridge && MODX_ROOT=/var/www/html composer build-schema && composer verify-generated-model'

docker compose -f "${ROOT}/docker-compose.yml" exec -T modx \
  bash -lc 'cd /workspace/modx-ai-bridge && MODX_ROOT=/var/www/html php _build/build.php'

echo "== 6. Install local Transport Package =="
docker compose -f "${ROOT}/docker-compose.yml" exec -T modx \
  bash -lc 'cd /workspace/modx-ai-bridge && MODX_ROOT=/var/www/html php scripts/install-package.php /var/www/html/core/packages/aibridge-0.1.0-alpha2.transport.zip'

echo "== 7. Verify runtime =="
docker compose -f "${ROOT}/docker-compose.yml" exec -T modx \
  bash -lc 'MODX_ROOT=/var/www/html php /workspace/modx-ai-bridge/scripts/verify-modx-runtime.php'

echo "== 8. Run PHPUnit =="
docker compose -f "${ROOT}/docker-compose.yml" exec -T modx \
  bash -lc 'cd /workspace/modx-ai-bridge && vendor/bin/phpunit'

echo "== MODX INTEGRATION: PASS =="
