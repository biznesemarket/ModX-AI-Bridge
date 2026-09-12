#!/usr/bin/env bash
#
# TypeScript SDK certification. Requires Node.js/npm with the TypeScript
# toolchain. When the toolchain is unavailable the gate reports BLOCKED and
# does not claim PASS (a missing runtime is a failed gate, not a pass).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if ! command -v node >/dev/null 2>&1 || ! command -v npm >/dev/null 2>&1; then
  echo "TYPESCRIPT SDK: BLOCKED (node/npm are not available in this environment)"
  exit 0
fi

cd "${ROOT}/sdk/typescript"
npm ci --no-audit --no-fund
npm run build
npm test
echo "TYPESCRIPT SDK: PASS"
