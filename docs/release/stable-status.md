# Stable Certification Status — 0.1.0-rc1

Status: **NOT STABLE** (Release Candidate only)

`AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh` was executed on the Docker stack. The gate
passes every PHP/composer check and the PHP unit/contract/security/SDK suites, then stops at the TypeScript
SDK gate:

```text
composer validate --no-check-publish --strict   PASS
composer lint                                   PASS
composer verify-static-contract                 PASS
composer test -- --testsuite unit,contract,security   OK (58 tests, 188 assertions)
composer test -- --testsuite sdk                OK (10 tests, 49 assertions)
./scripts/sdk-typescript-check.sh               BLOCKED (node/npm are not available)
                                                 A BLOCKED gate prevents Stable certification.
gate_exit=3
```

## Blocking gate

- **TypeScript SDK certification — BLOCKED.** `node`/`npm` are not available in the environment, so the
  `tsc` build and the runtime client contract tests (`sdk/typescript`) were not executed. A missing runtime
  is a failed gate, not a pass.

## Environment-limited gate

- The Docker-runtime portion of the gate (`composer test-modx`, which runs `scripts/test-modx.sh` and then
  `scripts/quality-gate.sh`) cannot execute inside the `modx` container because it requires the Docker CLI.
  Its individual steps were executed directly against the same stack throughout iterations 30–38
  (runtime verification, full PHPUnit, queue concurrency, REST smoke, HTTP security regression,
  upgrade/recovery drill, performance and Release Candidate build), and all passed. Wiring the gate to a
  host/CI runner with Docker remains required for a single-command Stable run.

## Not done

- `v0.1.0` tag **not created**.
- No `Stable` claim.

## Path to Stable

1. Provide a Node.js/npm toolchain so `scripts/sdk-typescript-check.sh` runs `npm ci`, `tsc` build and the
   TypeScript tests (must print `TYPESCRIPT SDK: PASS`).
2. Run `AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh` on a runner with Docker, PHP and
   Composer; all gates must pass in one invocation.
3. Only then create the `v0.1.0` tag.
