#!/bin/bash
set -euo pipefail

MODX_VERSION="${MODX_VERSION:-3.2.2-pl}"

if [ ! -f /var/www/html/config.core.php ]; then
  echo "[MODX] Creating MODX project ${MODX_VERSION}"
  rm -rf /var/www/html/* /var/www/html/.[!.]* /var/www/html/..?* 2>/dev/null || true
  composer create-project --no-interaction modx/revolution /tmp/modx "${MODX_VERSION}"
  cp -a /tmp/modx/. /var/www/html/
fi

if [ ! -f /var/www/html/config.core.php ]; then
  echo "[MODX] MODX files are present but not configured."
  echo "[MODX] Run the documented CLI setup/bootstrap step from scripts/modx-install.sh."
fi

exec "$@"
