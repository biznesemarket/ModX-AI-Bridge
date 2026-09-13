# CI Gates

The CI pipeline is intentionally split into deterministic and runtime jobs.

## Deterministic

- PHP syntax check (`composer lint`)
- Composer dependency validation (`composer validate --no-check-publish --strict`)
- static contract (`composer verify-static-contract`)
- PHPUnit `unit`, `contract` and `security` suites (contract coverage includes the API and MCP contracts)
- PHP SDK suite (`composer test -- --testsuite sdk`)
- TypeScript SDK build and contract type-check (`./scripts/sdk-typescript-check.sh`)
- supply-chain pin check (`bash scripts/verify-supply-chain-pins.sh`) — fails if any workflow `uses:` is not a
  40-character commit SHA, or if a Dockerfile/compose image is not referenced by `@sha256:` digest
- security suite that does not require external services

The deterministic job pins Node.js 24 via `actions/setup-node` with npm caching keyed on
`sdk/typescript/package-lock.json`, so a broken SDK build fails fast without the runtime job.

## Runtime

The Docker job provisions MySQL and MODX 3.2.x, installs the Extra, generates xPDO models, then executes integration and E2E suites. It also builds the transport package twice and asserts a byte-identical SHA-256 (`scripts/verify-package-reproducibility.php`, step 15 of `scripts/test-modx.sh`).

Provisioning readiness is explicit: `docker/modx/entrypoint.sh` writes `/var/www/html/.modx-ready` only after the MODX file tree is fully copied, and `scripts/test-modx.sh` waits for that marker. It must not wait for `config.core.php`, which appears during `cp -a` and can race the CLI install.

A runtime job must fail when a required runtime dependency is unavailable. It must not downgrade a missing MODX environment to a successful mock test.

## Evidence retention

The `Release` certification job uploads `dist/**` — the transport archive, its `.sha256` and
`<package>.release.json` — as the `release-evidence-<sha>` workflow artifact, so each certification run keeps
its checksum and release metadata even though `dist/` is not committed.

## Supply-chain pinning

Container base images and GitHub Actions are pinned to immutable references:

- `docker/modx/Dockerfile` — `FROM php:8.2-apache@sha256:…` and
  `COPY --from=composer:2@sha256:…`.
- `docker-compose.yml` and `deploy/docker/docker-compose.production.yml` — `mysql:8.0@sha256:…`.
- Every `uses:` in `.github/workflows/*.yml` references a full 40-character commit SHA, with the human-readable
  release tag in a trailing comment.

`.github/dependabot.yml` opens weekly pull requests that bump these pins (`github-actions` and `docker`
ecosystems), so the pins do not rot. Do not re-pin manually to a moving tag; resolve the digest/commit SHA,
update the pin, and re-run the full gate. The repository-level `sha_pinning_required` setting is not yet
enabled (tracked in the handoff as a follow-up).

## Release rule

`Stable` requires green deterministic and runtime gates. `Review` is the correct status when runtime execution has not occurred in the current environment.
