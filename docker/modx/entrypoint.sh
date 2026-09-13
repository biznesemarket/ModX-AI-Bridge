#!/bin/bash
set -euo pipefail

MODX_VERSION="${MODX_VERSION:-3.2.2-pl}"
MODX_ROOT="${MODX_ROOT:-/var/www/html}"
READY_MARKER="${MODX_ROOT}/.modx-ready"

# Remove a stale marker so the runtime gate never observes readiness mid-provision.
rm -f "${READY_MARKER}"

if [ ! -f "${MODX_ROOT}/config.core.php" ]; then
  echo "[MODX] Creating MODX project ${MODX_VERSION}"
  rm -rf "${MODX_ROOT}"/* "${MODX_ROOT}"/.[!.]* "${MODX_ROOT}"/..?* 2>/dev/null || true
  composer create-project --no-interaction modx/revolution /tmp/modx "${MODX_VERSION}"
  cp -a /tmp/modx/. "${MODX_ROOT}/"
fi

if [ ! -f "${MODX_ROOT}/config.core.php" ]; then
  echo "[MODX] MODX files are present but not configured."
  echo "[MODX] Run the documented CLI setup/bootstrap step from scripts/modx-install.sh."
fi

# Signal that the MODX file tree is fully materialized. The runtime gate waits for
# this marker instead of config.core.php, which can appear in the middle of `cp -a`
# and race the CLI installation step.
touch "${READY_MARKER}"

exec "$@"
