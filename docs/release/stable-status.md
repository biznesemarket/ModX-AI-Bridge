# Stable Certification Status

Status: **STABLE** — current release `0.3.0` (previous: `0.2.0`, `0.1.3`, `0.1.2`, `0.1.1`, `0.1.0`).

## 0.3.0 (current)

`AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh` completed green on GitHub Actions
`ubuntu-latest`, commit `dc3bd8a`, run `34756679369`:

```text
composer validate --no-check-publish --strict        PASS
composer lint                                        PASS
composer verify-static-contract                      PASS
composer test -- --testsuite unit,contract,security  OK (60 tests, 190 assertions)
composer test -- --testsuite sdk                     OK (11 tests, 51 assertions)
./scripts/sdk-typescript-check.sh                    TYPESCRIPT SDK: PASS
bash scripts/verify-supply-chain-pins.sh             SUPPLY CHAIN PINS: PASS
composer test-modx                                   == MODX INTEGRATION: PASS ==
  step 10b TypeScript SDK live HTTP E2E              TYPESCRIPT SDK LIVE HTTP: PASS
package reproducibility                              PACKAGE REPRODUCIBILITY: PASS
./scripts/quality-gate.sh                            PASS
STABLE certification gates passed.
```

- Release artifact: `aibridge-0.3.0.transport.zip`, SHA-256
  `9c3e547daace05e226480e3d31fe13e4d11a27ecd154e4eb8ac5b9125d132106` (142077 bytes); local build == CI
  artifact. README, SDK `User-Agent` and all version identities were committed before the gate.
- Minor release over `0.2.0`: template variables in the read-back projection (`tvs` map on
  `GET /resources/{id}` and the `resource_read` MCP tool), `resource.read` in the capability catalog and typed
  TypeScript SDK read-back types.
- Additive only: no schema/migration change, no new route/scope/setting, no API contract break.
- See `docs/release/0.3.0.md`.

## 0.2.0 (previous)

`AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh` completed green on GitHub Actions
`ubuntu-latest`, commit `350fc28`, run `34753117255`:

```text
composer validate --no-check-publish --strict        PASS
composer lint                                        PASS
composer verify-static-contract                      PASS
composer test -- --testsuite unit,contract,security  OK (60 tests, 190 assertions)
composer test -- --testsuite sdk                     OK (11 tests, 51 assertions)
./scripts/sdk-typescript-check.sh                    TYPESCRIPT SDK: PASS
bash scripts/verify-supply-chain-pins.sh             SUPPLY CHAIN PINS: PASS
composer test-modx                                   == MODX INTEGRATION: PASS ==
  step 10b TypeScript SDK live HTTP E2E              TYPESCRIPT SDK LIVE HTTP: PASS
package reproducibility                              PACKAGE REPRODUCIBILITY: PASS
./scripts/quality-gate.sh                            PASS
STABLE certification gates passed.
```

- Release artifact: `aibridge-0.2.0.transport.zip`, SHA-256
  `b8525cda8326f08102025f9e2f2f7c0bbf5ea9baaad52079175f4c1f7f11cc8e` (141585 bytes); local build == CI
  artifact. README, SDK `User-Agent` and all version identities were committed before the gate.
- Minor release over `0.1.3`: resource read-back API (`GET /resources/{id}`, `resource:read`, `resource_read`
  MCP tool, `getResource()` in both SDKs) and the packaged `aibridge_version` setting (20 settings).
- Additive only: no schema/migration change, no API contract break.
- See `docs/release/0.2.0.md`.

## 0.1.3 (previous)

`AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh` completed green on GitHub Actions
`ubuntu-latest`, commit `93fa4fd`, run `34751162661`:

```text
composer validate --no-check-publish --strict        PASS
composer lint                                        PASS
composer verify-static-contract                      PASS
composer test -- --testsuite unit,contract,security  OK (58 tests, 188 assertions)
composer test -- --testsuite sdk                     OK (11 tests, 50 assertions)
./scripts/sdk-typescript-check.sh                    TYPESCRIPT SDK: PASS
bash scripts/verify-supply-chain-pins.sh             SUPPLY CHAIN PINS: PASS
composer test-modx                                   == MODX INTEGRATION: PASS ==
  step 10b TypeScript SDK live HTTP E2E              TYPESCRIPT SDK LIVE HTTP: PASS
package reproducibility                              PACKAGE REPRODUCIBILITY: PASS
./scripts/quality-gate.sh                            PASS
STABLE certification gates passed.
```

- Release artifact: `aibridge-0.1.3.transport.zip`, SHA-256
  `0d66d8331b62df8ca1c9ce1b0d33e852771e862f1983d4e21a3728a4f1a93b1b` (139631 bytes); local build == CI
  artifact. README and all version identities were committed before the gate, so the hash is stable across
  the dispatch and tag runs.
- Patch release over `0.1.2`: TypeScript SDK live HTTP E2E (step 10b in `test-modx.sh`) and Defect #35
  (`waitForJob()` envelope fix in both SDKs).
- The published artifact is built and certified again by the `v0.1.3` tag run.
- See `docs/release/0.1.3.md`.

## 0.1.2 (previous)

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

- Release artifact: `aibridge-0.1.2.transport.zip`, SHA-256
  `eedd5f643fe97b378f5a4304fb3296b61a88db045d78302887417d88f8cb582d` (139585 bytes), reproduced locally from
  the tagged commit `c8ac594`; the pre-release dispatch run `34748277073` certified the same code and produced
  `b1b985bf…` before the release README update was embedded into the package manifest.
- The published artifact is built and certified again by the `v0.1.2` tag run `34748489085`
  (`STABLE certification gates passed.`).
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
