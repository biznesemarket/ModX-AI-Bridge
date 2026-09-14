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

echo "== 3. Wait for MODX files to be materialized =="
modx_ready=0
for i in $(seq 1 90); do
  if docker compose -f "${ROOT}/docker-compose.yml" exec -T modx test -f /var/www/html/.modx-ready 2>/dev/null; then
    modx_ready=1
    break
  fi
  sleep 2
done
if [ "${modx_ready}" != "1" ]; then
  echo "MODX entrypoint did not signal readiness (/var/www/html/.modx-ready missing)." >&2
  exit 1
fi

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
  bash -lc 'cd /workspace/modx-ai-bridge && MODX_ROOT=/var/www/html php scripts/install-package.php /var/www/html/core/packages/aibridge-0.10.0.transport.zip'

echo "== 7. Verify runtime =="
docker compose -f "${ROOT}/docker-compose.yml" exec -T modx \
  bash -lc 'MODX_ROOT=/var/www/html php /workspace/modx-ai-bridge/scripts/verify-modx-runtime.php'

echo "== 8. Run PHPUnit =="
docker compose -f "${ROOT}/docker-compose.yml" exec -T modx \
  bash -lc 'cd /workspace/modx-ai-bridge && MODX_ROOT=/var/www/html vendor/bin/phpunit'

echo "== 9. Queue concurrency harness =="
docker compose -f "${ROOT}/docker-compose.yml" exec -T modx \
  bash -lc 'MODX_ROOT=/var/www/html php /workspace/modx-ai-bridge/scripts/test-queue-concurrency.php'

echo "== 10. REST transport smoke =="
docker compose -f "${ROOT}/docker-compose.yml" exec -T modx \
  bash -lc 'MODX_ROOT=/var/www/html php /workspace/modx-ai-bridge/scripts/configure-test-runtime.php'

docker compose -f "${ROOT}/docker-compose.yml" exec -T modx \
  bash -lc 'set -e; code=$(curl -s -o /tmp/health.json -w "%{http_code}" http://localhost/api/ai/v2/health); test "$code" = "200"; grep -q "\"status\":\"ok\"" /tmp/health.json; code=$(curl -s -o /tmp/caps.json -w "%{http_code}" http://localhost/api/ai/v2/capabilities); test "$code" = "401"'

echo "== 10b. TypeScript SDK live HTTP E2E =="
bash "${ROOT}/scripts/ts-live-check.sh"

echo "== 11. HTTP security regression =="
docker compose -f "${ROOT}/docker-compose.yml" exec -T modx \
  bash -lc 'MODX_ROOT=/var/www/html php /workspace/modx-ai-bridge/scripts/security-regression-http.php'

echo "== 12. Upgrade/recovery drill =="
docker compose -f "${ROOT}/docker-compose.yml" exec -T modx \
  bash -lc 'cd /workspace/modx-ai-bridge && MODX_ROOT=/var/www/html bash scripts/recovery-drill.sh'

echo "== 13. Performance & limits =="
docker compose -f "${ROOT}/docker-compose.yml" exec -T modx \
  bash -lc 'cd /workspace/modx-ai-bridge && MODX_ROOT=/var/www/html php scripts/performance-limits.php'

echo "== 14. Release Candidate artifact =="
docker compose -f "${ROOT}/docker-compose.yml" exec -T modx \
  bash -lc 'cd /workspace/modx-ai-bridge && MODX_ROOT=/var/www/html php scripts/release-candidate.php'

echo "== 15. Package reproducibility =="
docker compose -f "${ROOT}/docker-compose.yml" exec -T modx \
  bash -lc 'cd /workspace/modx-ai-bridge && MODX_ROOT=/var/www/html php scripts/verify-package-reproducibility.php'

echo "== MODX INTEGRATION: PASS =="
