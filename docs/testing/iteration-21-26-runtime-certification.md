# Iterations 21–29 — Runtime Certification Evidence

Environment: Docker Desktop on Windows, `docker compose` stack from `docker-compose.yml` — MODX 3.2.2-pl,
PHP 8.2.33, MySQL 8.0. All commands were executed inside the `modx` container
(workdir `/workspace/modx-ai-bridge`), since the Windows host has no PHP/Composer/POSIX shell.

## Iteration 21 — baseline

- `composer validate --no-check-publish --strict` → `./composer.json is valid`
- `composer lint` → no syntax errors
- Test suites `unit`, `contract`, `security`, `integration` are defined in `phpunit.xml`; the duplicate
  combined suite was removed (it caused double execution and `include_once` processor failures).
- Version identity unified: `_build/config.inc.php` → `0.1.0-rc1`, README/STATUS/CHANGELOG updated.
- Defect register stored in `docs/ai-agent/baseline/known-defects.md`.

## Iteration 22 — runtime bring-up

- `docker compose up -d --build` — MODX 3.2.2-pl installed through `scripts/modx-install.sh`
  (legacy `setup/config.xml` accepted by MODX 3.2.2; no change required).
- Dockerfile was missing `libcurl4-openssl-dev` (ext-curl build) and `ext-ftp` (required by
  `league/flysystem-ftp` during `composer create-project modx/revolution`); `libssl-dev`, `pcntl` added.
- Apache mapping for `/api/ai/v2/*` is shipped in `docker/modx/apache/000-default.conf` (vhost rewrite;
  server-context rules are not inherited by the vhost) and `docker/modx/apache/aibridge-api.conf`.

## Iteration 23 — xPDO model, controllers, package bootstrap

- `MODX_ROOT=/var/www/html composer build-schema` generated `src/Model/*.php`,
  `src/Model/mysql/*.php`, `src/Model/metadata.mysql.php`; `composer verify-generated-model` → PASS.
- `scripts/xpdo.properties.inc.php` added: the xpdo CLI requires a `--config` file with connection options.
- `addPackage('AIBridge\Model', $corePath.'src/', null, 'AIBridge\\')` verified correct against xPDO 3.2
  (`setPackageMeta` resolves `src/Model/metadata.mysql.php`).
- Manager controllers made self-contained (`HomeManagerController`, `IndexManagerController`); the previous
  cross-file base class caused a fatal in MODX 3.
- Package resolvers now use the xPDO `$transport->xpdo` context; `modMenu`/`modSystemSetting` PKs are
  populated (`fromArray($data, '', true)`); `register-bootstrap.resolver.php` is wired into `_build/build.php`;
  `install-model.resolver.php` creates all 12 schema tables.

## Iteration 24 — deterministic gates and package install

- `composer verify-static-contract` → PASS (controller expectations moved to `controllers/`).
- `php scripts/install-package.php` → `Transport Package installation: PASS` (MODX 3 processor classes,
  `workspace`/`signature` properties, `.transport.zip` signature parsing).
- `php scripts/verify-modx-runtime.php` → PASS (namespace, xPDO models, service container, Manager menu,
  health processor).
- `vendor/bin/phpunit` with `MODX_ROOT=/var/www/html` → **OK (75 tests, 190 assertions)**;
  integration tests no longer skip.
- Fixed: `SecurityDecision::allowed()/code()` fluent accessors required by execution/MCP/rollback;
  `Application::securityDecision()` now passes `$modx`; settings prefix unified to `aibridge_` with
  `JSON_KEYS` decoding; `IpAllowlist` fails closed without rules.

## Iteration 25 — migrations

- Runner rewritten: raw PDO (`getConnection()->pdo`), no transactions around DDL, prefix substitution for
  both `[[+prefix]]` and `{PREFIX}`, explicit `{prefix}aibridge_migrations` ledger.
- `001_initial.sql` regenerated from the current xPDO schema; verified by applying `001` + `002` into a
  scratch database and diffing `mysqldump --no-data` output against the live resolver-created schema:
  **SCHEMA MATCH: migrations == live resolver schema**.
- `migrate.php migrate` → `applied 002_approval_workflow.sql`; rerun → `skipped ... (already applied)`;
  `status` shows both applied. Schema spot-check after migrate: `tokens.profile_id`, `jobs.locked_by` intact.
- `docs/deployment/migrations.md` documents the single-source-of-truth policy (resolver for fresh installs,
  SQL for upgrades).

## Iteration 26 — queue worker and concurrency

- `worker.php` implements a real CLI: claim loop, heartbeat support, handler registry, stale-lease requeue,
  graceful SIGTERM/SIGINT, `--once/--sleep/--max-iterations`. Executed: `worker.php --once` → exit 0.
- Queue manager raw SQL fixed for xPDO 3.2 (`$connection->pdo`), profile scoping preserved.
- `scripts/test-queue-concurrency.php` rewritten: dispatch → first claim wins → second claim empty →
  stale lease requeued → reclaimed → completed → **forked race with exactly one winner**.
- `scripts/test-modx.sh` now exports `MODX_ROOT` for PHPUnit and runs the harness and transport smoke.

## Iterations 27–29 — Manager, REST, MCP

- Manager resource operations require an explicit active `profile_id` (processor validates the profile and
  passes it to the change/execution principal); `manager.js` gained a profile selector.
- Raw PDO access fixed in `ResourceExecutionService` and `RollbackService`; legacy short class names
  (`modResource`, `modTemplateVar`, inspectors) replaced with MODX 3 FQCN.
- `RateLimiter`, `IdempotencyService`, `SnapshotService`, `AuditService` now persist the mandatory
  `profile_id` (audit read key fixed to `aibridge_audit_enabled`).
- REST boundary implemented: `AIBridge\Api\RestApi` + `assets/components/aibridge/api/index.php` with
  auth → rate limit → IP/authorization/policy/approval pipeline → idempotency contract →
  Application services; mutations return `202` with a job id.
- MCP endpoint implemented on the same front controller; `McpServer` keeps per-tool pipeline decisions,
  `resource_publish` now carries `change_id`, principal keeps `profile_id`.
- `tests/Integration/RestApiTest.php` (12 tests) and `McpTransportTest.php` (6 tests) certify:
  public health, 401 without token, scope denial, schema/fingerprint/contract/validate, idempotency-key
  requirement, delete/publish denial by default policy, create→queue→verification→resource exists,
  idempotent replay creating exactly one resource, cross-profile job isolation (404), REST/MCP disable.
- Transport smoke over Apache: `GET /api/ai/v2/health` → 200; `GET /api/ai/v2/capabilities` → 401.

## Remaining before Stable

See `docs/release/FINAL-INTEGRATION-STATUS.md`: update/delete/preview/publish E2E through approvals,
verification-mismatch rollback drill, full multi-site matrix, security regression runtime matrix,
observability pass, SDK certification, upgrade drill, performance, RC artifact and Stable tag.
