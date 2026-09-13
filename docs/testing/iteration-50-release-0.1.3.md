# Iteration 50 — `0.1.3` Patch Release

## Scope

Cut Iteration 49 (TypeScript SDK live HTTP E2E + Defect #35 fix) as the patch release `0.1.3`.

This release follows the `0.1.2` process lesson: **every** version identity — including `README.md`, which is
embedded into the transport package manifest — is bumped in the same commit **before** the certification gate,
so the pre-release and tag-run artifacts hash identically.

## Design

Version identities bumped `0.1.2` → `0.1.3`:

- build/package: `_build/config.inc.php`;
- runtime identity: `config/config.php`, `Application::health()`, `RestApi` `/health`,
  `processors/health.class.php`, `McpProtocol::initialize()` serverInfo, `OperationsConsoleService` and
  `scripts/readiness.php` fallbacks;
- SDKs: `sdk/php/src/Client.php`, `sdk/typescript/src/client.ts`, `sdk/typescript/package.json` +
  `package-lock.json`, runtime and live test assertions;
- installer path: `scripts/test-modx.sh` installs `aibridge-0.1.3.transport.zip`;
- docs: `README.md` (final text, no run ids so the manifest does not change after certification),
  `docs/development/transport-package.md`, `docs/testing/real-modx-integration.md`, CHANGELOG section.

## Local verification

```text
composer validate --no-check-publish --strict        valid
composer lint                                        no syntax errors
composer verify-static-contract                      Static contract: PASS
composer test -- --testsuite unit,contract,security  OK (58 tests, 188 assertions)
composer test -- --testsuite sdk                     OK (11 tests, 50 assertions)
bash scripts/sdk-typescript-check.sh                 TYPESCRIPT SDK: PASS (12 runtime tests)
bash scripts/ts-live-check.sh                        TYPESCRIPT SDK LIVE HTTP: PASS (11 tests)

# container: build + install + runtime
Created new transport package with signature: aibridge-0.1.3
Transport Package installation: PASS (Signature: aibridge-0.1.3)
MODX runtime verification: PASS (namespace, xPDO models, service container, menu, processor)
SELECT COUNT(*) ... aibridge_%                        19
PACKAGE REPRODUCIBILITY: PASS (aibridge-0.1.3, sha256 0d66d8331b62df8ca1c9ce1b0d33e852771e862f1983d4e21a3728a4f1a93b1b)
```

Operational note: after installing a new version into an already running local stack, PHP/Apache opcache keeps
serving the previous classes until `docker compose restart modx`; CI starts fresh containers and is unaffected
(see `docs/development/transport-package.md`).

## Certification

Pending: full `AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh` on CI. Evidence is appended after
the run; only then do the docs claim `Stable 0.1.3`.

## Notes

- No schema or migration change; no API contract change.
- `0.1.3` supersedes `0.1.2`; historical `v0.1.0`/`v0.1.1`/`v0.1.2` records stay untouched.
