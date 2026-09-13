# Iteration 52 — `0.2.0` Minor Release

## Scope

Cut Iteration 51 (resource read-back API + `aibridge_version`) as the minor release `0.2.0`. All version
identities, including `README.md` (embedded into the transport package manifest) and the SDK `User-Agent`
major.minor (`0.1` → `0.2`), are committed **before** the certification gate.

## Design

Bumped `0.1.3` → `0.2.0`:

- build/package: `_build/config.inc.php`, `_build/elements/settings.php` (`aibridge_version` default);
- runtime identity: `config/config.php`, `Application::health()`, `RestApi` `/health` + `/ready` fallbacks,
  `processors/health.class.php`, `McpProtocol::initialize()` serverInfo, `OperationsConsoleService` and
  `scripts/readiness.php` fallbacks;
- SDKs: `sdk/php/src/Client.php`, `sdk/php/src/HttpClient.php` (`modx-ai-bridge-sdk-php/0.2`),
  `sdk/typescript/src/client.ts` (`...-ts/0.2`), `package.json` + `package-lock.json`, runtime and live test
  assertions;
- installer path: `scripts/test-modx.sh` installs `aibridge-0.2.0.transport.zip`;
- docs: README (final text, no run ids), `transport-package.md`, `real-modx-integration.md`, CHANGELOG section.

## Local verification

```text
composer validate --no-check-publish --strict        valid
composer lint                                        no syntax errors
composer verify-static-contract                      Static contract: PASS
composer test -- --testsuite unit,contract,security  OK (60 tests, 190 assertions)
composer test -- --testsuite sdk                     OK (11 tests, 51 assertions)
bash scripts/sdk-typescript-check.sh                 TYPESCRIPT SDK: PASS (13 runtime tests)
MODX_ROOT=... composer test -- --testsuite integration  OK (58 tests, 2249 assertions)

# container: build + install + runtime
Transport Package installation: PASS (Signature: aibridge-0.2.0)
MODX runtime verification: PASS (namespace, xPDO models, service container, menu, processor)
curl /api/ai/v2/health                               {"status":"ok","version":"0.2.0",...}
curl /api/ai/v2/ready                                {"status":"ready","version":"0.2.0",...}
SELECT COUNT(*) ... aibridge_%                       20 (aibridge_version = 0.2.0)
PACKAGE REPRODUCIBILITY: PASS (aibridge-0.2.0, sha256 b8525cda8326f08102025f9e2f2f7c0bbf5ea9baaad52079175f4c1f7f11cc8e, 141585 bytes)

bash scripts/ts-live-check.sh                        TYPESCRIPT SDK LIVE HTTP: PASS (13 tests)
```

Operational notes:

- Do not run the integration suite and the live E2E in parallel on one local stack: both reconfigure
  `aibridge_ip_allowlist` and the live run then sees `403 ip_not_allowed`. CI runs them sequentially.
- After installing into a running stack, restart `modx` (opcache), and re-apply the test runtime settings
  because package installation re-imports setting defaults.

## Certification

Pending: full `AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh` on CI. Evidence is appended after
the run; only then do the docs claim `Stable 0.2.0`.

## Notes

- Additive minor: new route/scope/MCP tool/setting; no schema or migration change, no existing behaviour
  altered. Tokens need `resource:read` for read-back.
- `0.2.0` supersedes `0.1.3`; historical `v0.1.0`…`v0.1.3` records stay untouched.
