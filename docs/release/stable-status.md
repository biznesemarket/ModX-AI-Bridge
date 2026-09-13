# Stable Certification Status

Status: **STABLE** — current release `0.1.2` (previous: `0.1.1`, `0.1.0`).

## 0.1.2 (current)

`AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh` completed green on GitHub Actions
`ubuntu-latest`, commit `ad92ab5`, run `34748277073`:

```text
composer validate --no-check-publish --strict        PASS
composer lint                                        PASS
composer verify-static-contract                      PASS
composer test -- --testsuite unit,contract,security  OK (58 tests, 188 assertions)
composer test -- --testsuite sdk                     OK (10 tests, 49 assertions)
./scripts/sdk-typescript-check.sh                    TYPESCRIPT SDK: PASS
bash scripts/verify-supply-chain-pins.sh             SUPPLY CHAIN PINS: PASS
composer test-modx                                   == MODX INTEGRATION: PASS ==
package reproducibility                              PACKAGE REPRODUCIBILITY: PASS
./scripts/quality-gate.sh                            PASS
STABLE certification gates passed.
```

- Certified artifact: `aibridge-0.1.2.transport.zip`, SHA-256
  `b1b985bffdd3a661bd3f99cfa1b333fee854a5339b0fb0d34468e1575b9acf94` (local build == CI artifact).
- Patch release over `0.1.1`: deterministic provisioning marker, TypeScript SDK runtime tests and
  supply-chain pinning (images by digest, actions by SHA, `sha_pinning_required=true`).
- See `docs/release/0.1.2.md`.

## 0.1.1 (previous)

Certified on commit `db949b5`, run `34743692154`; artifact `aibridge-0.1.1.transport.zip` (SHA-256
`26ccdee1fb27097694dc735795f86e2c75a66136310e2a1e9ca248465c7a729a`). Fixes Defect #34 and makes the
transport build reproducible; see `docs/release/0.1.1.md`.

## 0.1.0 (previous)

Certified on commit `160a659`, run `34716441523`; artifact `aibridge-0.1.0.transport.zip`
(SHA-256 `859c659994e9b3ef41759e9df4aeeee98b795fbf613e27541d730db82e377340`). It predates the reproducible
build and the settings packaging fix; see `docs/release/0.1.0.md`.

## Environment

- Runner: GitHub Actions `ubuntu-latest` (Ubuntu 24.04 image).
- Runtime: Docker MODX 3.2.2-pl, PHP 8.2.33, MySQL 8.0, zlib 1.3.1.
- Node.js/npm: provisioned by `actions/setup-node` (Node 24); `tsc` build and client contract type-check.

## Prior blocking state (resolved)

Iteration 39 left the release NOT STABLE because the TypeScript SDK gate was BLOCKED (no `node`/`npm`) and
the single-command gate could not run in-container. Iterations 40–41 resolved both; Iteration 43 made builds
reproducible; Iteration 44 fixed the settings packaging defect and released `0.1.1`.
