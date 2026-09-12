#!/usr/bin/env bash
set -euo pipefail
: "${MODX_ROOT:?Set MODX_ROOT to a real MODX installation}"
php -l _build/build.php >/dev/null
MODX_ROOT="$MODX_ROOT" php _build/build.php
PKG=$(find "$MODX_ROOT/core/packages" -maxdepth 1 -type f -name 'aibridge-*.transport.zip' -printf '%T@ %p\n' | sort -nr | head -1 | cut -d' ' -f2-)
if [[ -z "${PKG:-}" || ! -f "$PKG" ]]; then echo "Transport Package not found" >&2; exit 1; fi
sha256sum "$PKG"
echo "Release artifact: $PKG"
