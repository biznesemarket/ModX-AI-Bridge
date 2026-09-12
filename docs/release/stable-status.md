# Stable Certification Status — 0.1.0

Status: **STABLE**

`AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh` completed green in a single invocation on
GitHub Actions `ubuntu-latest` (Docker + PHP 8.2 + Composer + Node.js), commit `160a659`, run `34716441523`:

```text
composer validate --no-check-publish --strict        PASS
composer lint                                        PASS
composer verify-static-contract                      PASS
composer test -- --testsuite unit,contract,security  OK (58 tests, 188 assertions)
composer test -- --testsuite sdk                     OK (10 tests, 49 assertions)
./scripts/sdk-typescript-check.sh                    TYPESCRIPT SDK: PASS
composer test-modx                                   == MODX INTEGRATION: PASS == (123 tests, 626 assertions)
./scripts/quality-gate.sh                            PASS
STABLE certification gates passed.
```

## Certified artifact

- Stable Transport Package: `aibridge-0.1.0.transport.zip` (built by `scripts/release-candidate.php`).
- The build first passed on commit `642c681` (TypeScript gate cleared, run `34715583238`) and again on the
  finalized `0.1.0` package at commit `160a659` (run `34716441523`).
- Transport archives are not byte-reproducible (embedded file timestamps); each build records its own
  SHA-256 in `dist/<package>.release.json` and `<package>.sha256`. See `docs/release/0.1.0.md`.

## Environment

- Runner: GitHub Actions `ubuntu-latest` (Ubuntu 24.04 image).
- Runtime: Docker MODX 3.2.2-pl, PHP 8.2, MySQL 8.0.
- Node.js/npm: provided by the runner; `tsc` build and client contract type-check.

## Release

- Tag `v0.1.0` points at the certified commit.
- `git push` of the tag re-runs `release.yml` (verify → certify → package) as the official release gate.

## Prior blocking state (resolved)

Iteration 39 left the release NOT STABLE because the TypeScript SDK gate was BLOCKED (no
`node`/`npm`) and the single-command gate could not run in-container. Both were resolved in Iterations 40–41:
toolchain provided, SDK lockfile/build fixed, executable bit corrected, and the gate run on a proper runner.
