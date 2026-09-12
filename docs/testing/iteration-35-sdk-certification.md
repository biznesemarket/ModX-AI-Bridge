# Iteration 35 — SDK Certification Evidence

Environment: Docker Desktop on Windows, `docker compose` stack from `docker-compose.yml` — MODX 3.2.2-pl,
PHP 8.2.33, MySQL 8.0. Commands executed inside the `modx` container.

## Scope

PHP and TypeScript SDK contract certification against the OpenAPI document.

## Changes

- `docs/api/openapi.yaml` aligned with the implemented/served surface: added `/health`, `/ready`,
  `/profiles`, `/resources/preview`, `/resources/{id}/publish`; mutations document the `Idempotency-Key`
  header and the `202 Job queued` response.
- Root `composer.json` autoloads `AIBridge\SDK\` from `sdk/php/src/`; `phpunit.xml` gained an `sdk`
  testsuite so the SDK contract tests run with the main suite.
- Expanded `sdk/php/tests/ClientContractTest.php` and added `sdk/php/tests/OpenApiContractTest.php`.
- Added `scripts/sdk-typescript-check.sh` (checks `tsc` build/tests, reports BLOCKED without Node);
  `scripts/certification/stable-gate.sh` now runs the `sdk` testsuite.

## Evidence

- `MODX_ROOT=/var/www/html vendor/bin/phpunit --testsuite sdk` → **OK (10 tests, 49 assertions)**:
  - create/update/delete carry `Idempotency-Key` and correct method/path/body;
  - publish carries `approval_id`; preview and content validation contracts;
  - read contracts (`capabilities`, `profiles`, `site/schema`, `site/fingerprint`, `content/contract`,
    `jobs/{id}`);
  - `waitForJob` returns a terminal state and raises a typed `ApiException(408, job_timeout)` on timeout;
  - `Idempotency::key()` is random 32-hex;
  - every SDK path is present in `docs/api/openapi.yaml`, the `Idempotency-Key` header is documented for
    each mutation, and mutations document the `202` response.
- `MODX_ROOT=/var/www/html vendor/bin/phpunit` → **OK (123 tests, 1840 assertions)**.
- `composer validate --no-check-publish --strict` / `verify-static-contract` / `verify-generated-model` → PASS.
- `bash scripts/sdk-typescript-check.sh` → **BLOCKED**: `node`/`npm` are not available in the container, so
  the TypeScript `tsc` build and runtime client tests were not executed. A missing toolchain is not a PASS.

## Notes / remaining

- The TypeScript SDK source and `package.json` scripts (`build`, `test` = `tsc --noEmit`) are present but
  uncertified until a Node toolchain is available; the plan treats this as BLOCKED, and a BLOCKED gate
  prevents `Stable`.
- Package upgrade/rollback drill, performance/limits, RC artifact and the Stable tag remain for
  Iterations 36–39.
