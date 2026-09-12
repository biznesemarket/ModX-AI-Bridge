#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

php -v
composer validate --no-check-publish --strict
composer lint
composer verify-static-contract
composer test -- --testsuite unit,contract,security
composer test -- --testsuite sdk
./scripts/sdk-typescript-check.sh

if [[ "${AIBRIDGE_RUNTIME:-0}" == "1" ]]; then
  composer test-modx
else
  echo "Runtime MODX gate not requested. Set AIBRIDGE_RUNTIME=1 to require Docker E2E."
fi
