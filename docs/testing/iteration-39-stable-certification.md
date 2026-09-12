# Iteration 39 — Stable Certification Attempt Evidence

Environment: Docker Desktop on Windows, `docker compose` stack from `docker-compose.yml` — MODX 3.2.2-pl,
PHP 8.2.33, MySQL 8.0. Gate executed inside the `modx` container.

## Scope

Attempt the Stable certification gate and record the outcome honestly, including BLOCKED gates. Do not
declare Stable and do not create a release tag when any gate is BLOCKED.

## Changes

- `scripts/sdk-typescript-check.sh` now exits non-zero (3) when the Node toolchain is missing, so a BLOCKED
  TypeScript gate actually blocks certification instead of silently passing.
- `scripts/certification/stable-gate.sh` and `scripts/quality-gate.sh` now run the PHP `sdk` testsuite and
  the TypeScript SDK gate.
- Added `docs/release/stable-status.md`.

## Evidence

`AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh` →

```text
composer validate --no-check-publish --strict   PASS
composer lint                                   PASS
composer verify-static-contract                 PASS
composer test -- --testsuite unit,contract,security   OK (58 tests, 188 assertions)
composer test -- --testsuite sdk                OK (10 tests, 49 assertions)
TYPESCRIPT SDK: BLOCKED (node/npm are not available in this environment)
A BLOCKED gate prevents Stable certification.
gate_exit=3
```

Result: **NOT STABLE**. The gate fails closed at the TypeScript SDK gate.

## Not executed / BLOCKED

- TypeScript SDK `tsc` build and runtime client tests — BLOCKED (no `node`/`npm`).
- The Docker-runtime portion (`composer test-modx` + `quality-gate.sh`) cannot run inside the container
  (no Docker CLI). Its steps were executed directly against the same stack in iterations 30–38 and all
  passed; a host/CI runner with Docker is still required for a single-command Stable run.

## Outcome

- Release Candidate `0.1.0-rc1` remains the latest artifact.
- `v0.1.0` tag not created; `Stable` not claimed. See `docs/release/stable-status.md` and
  `docs/release/FINAL-INTEGRATION-STATUS.md`.
