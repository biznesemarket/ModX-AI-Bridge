#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

: "${AIBRIDGE_RUNTIME:=0}"

php -v
composer validate --no-check-publish --strict
composer lint
composer verify-static-contract
composer test -- --testsuite unit,contract,security
composer test -- --testsuite sdk

if [[ "$AIBRIDGE_RUNTIME" != "1" ]]; then
  echo "STABLE certification requires AIBRIDGE_RUNTIME=1" >&2
  exit 2
fi

composer test-modx
./scripts/quality-gate.sh
printf '%s\n' 'STABLE certification gates passed.'
