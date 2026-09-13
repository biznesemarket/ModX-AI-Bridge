#!/usr/bin/env bash
set -euo pipefail

# TypeScript SDK live HTTP E2E against a running MODX stack.
#
# Requires the docker compose stack to be up (docker compose up -d) and Node.js
# on the host. The test runtime allowlist is widened to the container gateway
# for the duration of the run and restored afterwards. Never run this against
# production; it creates a test profile, token and MODX resources.

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
if command -v cygpath >/dev/null 2>&1; then
  # Git Bash/MSYS would rewrite container paths like /var/www/html; keep args verbatim.
  export MSYS2_ARG_CONV_EXCL='*'
  COMPOSE_FILE="$(cygpath -w "${ROOT}/docker-compose.yml")"
else
  COMPOSE_FILE="${ROOT}/docker-compose.yml"
fi
COMPOSE=(docker compose -f "${COMPOSE_FILE}")

command -v node >/dev/null 2>&1 || { echo "Node.js is required for the TypeScript SDK live HTTP E2E." >&2; exit 1; }
docker compose version >/dev/null 2>&1 || { echo "docker compose is required." >&2; exit 1; }

MODX_CONTAINER="$("${COMPOSE[@]}" ps -q modx)"
if [ -z "${MODX_CONTAINER}" ]; then
  echo "The MODX container is not running; start the stack before the live E2E." >&2
  exit 1
fi

GATEWAY="$(docker inspect --format '{{range .NetworkSettings.Networks}}{{.Gateway}} {{end}}' "${MODX_CONTAINER}" | awk '{print $1}')"
if [ -z "${GATEWAY}" ]; then
  echo "Unable to determine the container gateway address." >&2
  exit 1
fi

ALLOWLIST="[\"127.0.0.1\",\"${GATEWAY}\"]"
WORKER_PID=""

cleanup() {
  if [ -n "${WORKER_PID}" ]; then
    "${COMPOSE[@]}" exec -T modx bash -lc 'touch /tmp/ts-live-worker-stop' >/dev/null 2>&1 || true
    wait "${WORKER_PID}" 2>/dev/null || true
    kill "${WORKER_PID}" 2>/dev/null || true
  fi
  "${COMPOSE[@]}" exec -T modx bash -lc 'rm -f /tmp/ts-live-worker-stop; MODX_ROOT=/var/www/html php /workspace/modx-ai-bridge/scripts/ts-live-runtime.php --restore-allowlist' >/dev/null 2>&1 || true
}
trap cleanup EXIT

CONTEXT="$("${COMPOSE[@]}" exec -T -e "AIBRIDGE_TEST_IP_ALLOWLIST=${ALLOWLIST}" modx \
  bash -lc 'MODX_ROOT=/var/www/html php /workspace/modx-ai-bridge/scripts/ts-live-runtime.php')"

cd "${ROOT}/sdk/typescript"
npm ci --no-audit --no-fund >/dev/null
npm run build >/dev/null

"${COMPOSE[@]}" exec -T modx bash -lc 'rm -f /tmp/ts-live-worker-stop' >/dev/null 2>&1 || true
"${COMPOSE[@]}" exec -T modx bash -lc 'while [ ! -f /tmp/ts-live-worker-stop ]; do MODX_ROOT=/var/www/html php core/components/aibridge/worker.php --once >/dev/null 2>&1 || exit 1; sleep 1; done' &
WORKER_PID=$!

AIBRIDGE_LIVE_CONTEXT="${CONTEXT}" node --test --test-concurrency=1 tests/live/live.test.mjs

echo "TYPESCRIPT SDK LIVE HTTP: PASS"
