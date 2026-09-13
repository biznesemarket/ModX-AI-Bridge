# CI Gates

The CI pipeline is intentionally split into deterministic and runtime jobs.

## Deterministic

- PHP syntax check (`composer lint`)
- Composer dependency validation (`composer validate --no-check-publish --strict`)
- static contract (`composer verify-static-contract`)
- PHPUnit `unit`, `contract` and `security` suites (contract coverage includes the API and MCP contracts)
- PHP SDK suite (`composer test -- --testsuite sdk`)
- TypeScript SDK build and contract type-check (`./scripts/sdk-typescript-check.sh`)
- security suite that does not require external services

The deterministic job pins Node.js 24 via `actions/setup-node` with npm caching keyed on
`sdk/typescript/package-lock.json`, so a broken SDK build fails fast without the runtime job.

## Runtime

The Docker job provisions MySQL and MODX 3.2.x, installs the Extra, generates xPDO models, then executes integration and E2E suites. It also builds the transport package twice and asserts a byte-identical SHA-256 (`scripts/verify-package-reproducibility.php`, step 15 of `scripts/test-modx.sh`).

A runtime job must fail when a required runtime dependency is unavailable. It must not downgrade a missing MODX environment to a successful mock test.

## Evidence retention

The `Release` certification job uploads `dist/**` — the transport archive, its `.sha256` and
`<package>.release.json` — as the `release-evidence-<sha>` workflow artifact, so each certification run keeps
its checksum and release metadata even though `dist/` is not committed.

## Release rule

`Stable` requires green deterministic and runtime gates. `Review` is the correct status when runtime execution has not occurred in the current environment.
