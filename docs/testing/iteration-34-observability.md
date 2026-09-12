# Iteration 34 — Observability & Readiness Evidence

Environment: Docker Desktop on Windows, `docker compose` stack from `docker-compose.yml` — MODX 3.2.2-pl,
PHP 8.2.33, MySQL 8.0. Commands executed inside the `modx` container.

## Scope

Request-id propagation, structured error envelopes, worker audit correlation, secret redaction in logs, and
a public readiness signal.

## Changes

- `RestApi` accepts a bounded inbound `X-Request-Id` (`[A-Za-z0-9._:-]{1,128}`), otherwise generates one,
  echoes it on every response (`X-Request-Id`) and reuses it in bodies/audit.
- `RestApi` exposes a public `GET /ready` readiness endpoint (MODX, database, service container, namespace)
  that works regardless of `rest_enabled`, returning 200/503.
- `Worker` audit events (`job_completed`/`job_failed`) now include `request_id`, so job execution can be
  correlated back to the originating request.
- `SecretRedactor::redactText()` redacts bearer tokens and `key=value` secrets in free-form log lines; the
  worker CLI uses it for error/requeue output.
- `scripts/readiness.php` now checks the service container and namespace via MODX 3 FQCN and reports the
  component version.

## Evidence

- `MODX_ROOT=/var/www/html vendor/bin/phpunit --filter "ObservabilityRuntimeTest|SecretRedactorTest"` →
  **OK (6 tests)**:
  1. inbound `X-Request-Id` is echoed in the body and response header; an invalid id is replaced with a
     generated 32-hex id;
  2. a structured error envelope (`code`/`message`/`details`/`request_id`) is returned for an auth failure;
  3. a dispatched job stores the inbound `request_id` and the worker `job_completed` audit row carries it;
  4. `GET /ready` returns 200 `ready` with database + namespace checks;
  5. free-text redaction removes bearer tokens and `key=value` secrets.
- `MODX_ROOT=/var/www/html vendor/bin/phpunit --testsuite unit,contract,security` → **OK (58 tests, 188 assertions)**.
- `MODX_ROOT=/var/www/html vendor/bin/phpunit` → **OK (113 tests, 1645 assertions)**.
- HTTP: `GET /api/ai/v2/ready` → 200 ready; `GET /api/ai/v2/health` with `X-Request-Id: smoke-42` echoes
  the header.
- `composer lint` / `verify-static-contract` / `verify-generated-model` → PASS;
  `scripts/security-regression-http.php` → PASS.

## Notes / remaining

- SDK certification (PHP + TypeScript), package upgrade/rollback drill, performance/limits, RC artifact and
  the Stable tag remain for Iterations 35–39.
