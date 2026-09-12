#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

command -v docker >/dev/null 2>&1 || {
  echo "FINAL STABILIZATION BLOCKED: Docker is required for runtime certification." >&2
  exit 20
}
docker compose version >/dev/null 2>&1 || {
  echo "FINAL STABILIZATION BLOCKED: Docker Compose is required for runtime certification." >&2
  exit 20
}

php "${ROOT}/tests/Integration/preflight.php"
find "${ROOT}/core" "${ROOT}/tests" -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
find "${ROOT}" -name '*.sh' -print0 | xargs -0 -n1 bash -n

KEEP_MODX_STACK=1 bash "${ROOT}/scripts/test-modx.sh"
php "${ROOT}/scripts/release-package.sh"

echo "FINAL STABILIZATION: runtime gates completed. Review release evidence before tagging Stable."
