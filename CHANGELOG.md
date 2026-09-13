# Changelog

## Unreleased

- Read-back API: `GET /api/ai/v2/resources/{id}` returns a whitelisted resource projection through the
  SecurityDecisionPipeline (new scope `resource:read`, `resource_not_found` -> 404). Added `getResource()` to
  the PHP and TypeScript SDKs and the `resource_read` MCP tool; the live HTTP E2E now verifies the state after
  an update instead of parsing job results.
- New `aibridge_version` system setting (area `operations`, 20 settings packaged). `/health` and `/ready`
  report the version from the setting with a code fallback; `#34`-style packaging covered by the settings
  gate.

## 0.1.3 — 2026-09-13

- TypeScript SDK live HTTP E2E: `scripts/ts-live-check.sh` + `scripts/ts-live-runtime.php` run the compiled SDK
  against a real MODX `/api/ai/v2/*` boundary (11 `node:test` cases: auth/401, capabilities, profile
  isolation, schema/fingerprint, content contract/validation, resource create/update through the queue,
  delete/publish policy denials, MCP). Wired into `scripts/test-modx.sh` as step 10b; `npm test` stays
  offline, `npm run test:live` is the live suite.
- Fixed Defect #35 found by that E2E: `waitForJob()` in the PHP and TypeScript SDKs only read
  `data.status`/`status`, while `GET /api/ai/v2/jobs/{id}` returns `{success, job:{status}}`, so polling
  timed out against the real service. Both clients now read `job.status`; runtime tests lock the shape.

## 0.1.2 — 2026-09-13

- Deterministic MODX provisioning: `docker/modx/entrypoint.sh` writes `/var/www/html/.modx-ready` only
  after the file tree is fully materialized, and `scripts/test-modx.sh` waits for that marker instead of
  `config.core.php` — the latter could appear in the middle of `cp -a` and race the CLI install step
  (`setup/config.xml: No such file or directory`).
- TypeScript SDK runtime tests: `sdk/typescript/tests/runtime/client.test.mjs` exercises the compiled client
  against a fake `fetch` (auth/JSON headers, idempotency keys, `updateResource` id merge, error mapping,
  `waitForJob` polling/timeout, MCP JSON-RPC envelopes). `npm test` runs them with `node --test`; no new
  dependencies, and `scripts/sdk-typescript-check.sh` now covers build + runtime tests + types.
- Supply-chain pinning: container images are referenced by digest (`php:8.2-apache`, `composer:2` in
  `docker/modx/Dockerfile`; `mysql:8.0` in the compose files) and every GitHub Actions `uses:` is pinned to a
  commit SHA with a trailing tag comment. `.github/dependabot.yml` raises weekly `github-actions` and `docker`
  update PRs so the pins do not rot.

## 0.1.1 — 2026-09-13

- Fixed the transport package dropping all system settings: `_build/elements/settings.php` chained
  `fromArray()` off `new modSystemSetting()`, but `xPDOObject::fromArray()` returns void, so every entry was
  `null` and `build.php` skipped it. The package now ships all 19 `aibridge_*` settings. Releases `0.1.0`
  and earlier did not install them (the application fell back to code defaults).
- CI hardening: the `Quality Gates` `deterministic` job now runs the PHP `sdk` suite and the TypeScript SDK
  gate (`./scripts/sdk-typescript-check.sh`) with a pinned Node.js 24 toolchain, so a broken SDK build fails
  fast; workflows moved to Node.js 24 action runtimes (`actions/checkout@v7`, `actions/setup-node@v7`,
  `ramsey/composer-install@v4`); the `Release` certification job uploads `dist/**` (archive, `.sha256`,
  release metadata) as a `release-evidence-<sha>` workflow artifact.
- Reproducible transport builds: `_build/build.php` assigns deterministic vehicle guids (xPDO otherwise uses
  `md5(uniqid(rand(), true))`) and rewrites the archive with sorted file entries (directory entries dropped),
  a fixed `SOURCE_DATE_EPOCH` timestamp, so repeated builds of the same sources are byte-identical;
  `scripts/verify-package-reproducibility.php` (step 15 of `scripts/test-modx.sh`) asserts this.

## 0.1.0 — Stable (2026-09-12)

- Unblocked TypeScript SDK certification: added the missing `sdk/typescript/package-lock.json`, fixed
  NodeNext import resolution (`./errors.js`), widened idempotency-key parameters to `string`, ignored SDK
  build artifacts, and marked `scripts/sdk-typescript-check.sh` executable (it is invoked via `./`). CI now
  reports `TYPESCRIPT SDK: PASS`.
- Added `workflow_dispatch` to `.github/workflows/release.yml` so the full certification gate can be run on
  demand; `AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh` passes end to end on a
  Docker + PHP + Composer + Node runner and prints `STABLE certification gates passed.`
- Finalized the Stable transport package: `_build/config.inc.php` release suffix removed and signature
  composition made suffix-safe, so the build produces `aibridge-0.1.0.transport.zip`
  (`scripts/test-modx.sh` updated to install it).
- Declared `Stable`; tag `v0.1.0`. See `docs/release/0.1.0.md`, `docs/release/stable-status.md`,
  `docs/testing/iteration-40-typescript-sdk-unblock.md` and `docs/testing/iteration-41-stable-release.md`.

## Unreleased — Stabilization (0.1.0-rc1)

- Defined named PHPUnit test suites: unit, contract, security, integration.
- Rewrote security regression tests against the actual Bridge configuration contract.
- Replaced tautological approval-workflow and application tests with real security and scope-mapping assertions.
- Unified version/status across README, CHANGELOG, testing status and build configuration (0.1.0-rc1 target).
- Runtime certification bring-up on MODX 3.2.2-pl + MySQL 8 (Docker):
  - generated and committed the xPDO 3.2 model (`src/Model`, `src/Model/mysql`, `src/Model/metadata.mysql.php`);
  - fixed `SecurityDecision` fluent accessors (`allowed()`, `code()`) required by execution/MCP/rollback layers;
  - fixed raw PDO access for the xPDO 3.2 connection wrapper in QueueManager, ResourceExecutionService,
    RollbackService and the migration runner;
  - replaced legacy MODX 2 class names (`modResource`, `modTemplateVar`, inspectors) with MODX 3 FQCN;
  - added mandatory `profile_id` persistence in rate limiter, idempotency, snapshots and audit;
  - fixed `aibridge_` settings prefix consumption and JSON array settings decoding; completed the settings set;
  - Manager controllers are self-contained MODX 3 controllers; menu/setting primary keys are populated;
  - package resolvers use the xPDO `$transport->xpdo` context; bootstrap resolver wired; all 12 tables created;
  - package installer uses MODX 3 processor classes; `verify-modx-runtime` and full PHPUnit pass with MODX_ROOT;
  - fixed `ContentQAService` duplicate SEO errors and JSON-LD handling in the script-tag check;
  - `IpAllowlist` now fails closed (empty list denies non-manager requests);
  - migration runner: prefix substitution for both placeholder syntaxes, DDL-safe execution, idempotent ledger;
  - implemented the queue worker CLI and a real queue concurrency harness (atomic claim, stale lease, forked race);
  - Manager resource operations require an explicit active `profile_id`;
  - implemented the REST boundary (`/api/ai/v2/*`) and MCP HTTP endpoint with authentication, rate limiting,
    idempotency contract, job dispatch and profile isolation, including integration tests and an HTTP smoke test;
  - model generation is deterministic again: stale platform classes are recreated from scratch (xPDO `--update`
    could write unresolved template markers) and `verify-generated-model` now validates class contents;
  - integration gates apply a deterministic test runtime configuration with a system settings cache refresh.
- Iteration 30 — mutation E2E and rollback certification:
  - fixed `ResourceRollbackProcessor`: it now enforces the `aibridge_manage` permission and builds a
    manager principal (`manager_authorized`, `scopes`, `profile_id`) with `channel=manager`, so Manager
    rollback reaches the security pipeline instead of being denied (Defect #7);
  - publish expected state now includes `published` and a non-empty `publishedon` marker; `tvs` keys are
    normalized — `VerificationService` gained `PRESENT` sentinel handling and boolean-scalar normalization
    for coerced xPDO fields (Defect #25);
  - added `tests/Integration/ResourceMutationE2ETest.php` covering Manager update/preview/delete/publish
    through approvals, post-execution verification, controlled mismatch → `NonRetryableJobException`,
    snapshot rollback of fields/TVs and refusal to recreate a deleted resource.
- Iteration 31 — approval workflow E2E certification:
  - full `ChangeState` transition matrix and terminal-state invariants covered by unit tests;
  - added `tests/Integration/ApprovalWorkflowE2ETest.php`: `draft→submit→reject`, approval↔change binding
    (an approval cannot authorize another change), execute-only-approved guards, approve→execute→verify→
    audit happy path with terminal completed state, approval decision lifecycle and manager publish
    denied without an approval reference.
- Iteration 32 — multi-site isolation certification:
  - `TokenAuthenticator` now rejects tokens whose profile is missing or not active
    (`token_profile_missing` / `profile_inactive`);
  - snapshot rollback, change transitions, approval creation/decision and approved execution enforce
    server-side profile ownership (`profile_mismatch` / `belongs to another profile`);
  - added `tests/Integration/MultiSiteIsolationE2ETest.php` covering the Token/Job/Audit/Schema/
    Fingerprint/Snapshot/Change/Approval cross-profile matrix and IDOR attempts (9 tests).
- Iteration 33 — runtime security regression:
  - the REST front controller rejects malformed JSON bodies with `400 invalid_json` instead of treating
    them as an empty object;
  - added `tests/Integration/SecurityRegressionRuntimeTest.php` (auth bypass, scope escalation, profile
    escape, rate limit 429 + `Retry-After`, idempotency conflict/replay, secret/audit leakage);
  - added `scripts/security-regression-http.php` (malformed JSON 400, oversized body 413, transport auth)
    wired into `scripts/test-modx.sh`.
- Iteration 34 — observability and readiness:
  - `RestApi` propagates a bounded inbound `X-Request-Id` (or generates one) and echoes it on every
    response, and exposes a public `GET /ready` readiness endpoint (200/503);
  - `Worker` audit events include `request_id`; the worker CLI redacts secrets in error/requeue output via
    `SecretRedactor::redactText()`;
  - `scripts/readiness.php` reports service-container/namespace checks and the component version.
- Iteration 35 — SDK certification:
  - aligned `docs/api/openapi.yaml` with the served surface (added `/health`, `/ready`, `/profiles`,
    `/resources/preview`, `/resources/{id}/publish`; mutations document `202` and `Idempotency-Key`);
  - `AIBridge\SDK\` is autoloaded from `sdk/php/src/` and `sdk` is a PHPUnit testsuite; expanded PHP SDK
    contract tests and added an OpenAPI alignment test;
  - added `scripts/sdk-typescript-check.sh`; the TypeScript SDK remains **BLOCKED** because `node`/`npm`
    are not available (not certified as PASS).
- Iteration 36 — upgrade/recovery drill:
  - added `scripts/recovery-drill.sh` (baseline migrate, checksummed `mysqldump` backup, additive schema
    upgrade, runtime smoke, restore and verification of schema/data/migration ledger), wired into
    `scripts/test-modx.sh`;
  - documented the executable drill in the upgrade/rollback and backup/restore runbooks.
- Iteration 37 — performance & limits:
  - added `scripts/performance-limits.php` measuring large-resource save, 50-TV save, snapshot creation,
    queue dispatch/claim depth (200), rate-limiter throughput (500) and audit growth (500 write / 100 read)
    against per-operation budgets; wired into `scripts/test-modx.sh`.
- Iteration 38 — Release Candidate:
  - added `scripts/release-candidate.php` (build, archive-content verification, SHA-256/size, MODX/PHP/
    migration-level metadata, `dist/<package>.release.json` + `.sha256`); wired into `scripts/test-modx.sh`;
  - recorded the `0.1.0-rc1` release in `docs/release/0.1.0-rc1.md`. `Stable` is withheld because the
    TypeScript SDK gate is BLOCKED.
- Iteration 39 — Stable certification attempt:
  - `scripts/sdk-typescript-check.sh` now exits non-zero when the Node toolchain is missing, so a BLOCKED
    TypeScript gate blocks certification; `stable-gate.sh` and `quality-gate.sh` run the `sdk` testsuite
    and the TypeScript gate;
  - result: **NOT STABLE** — the gate fails closed at the TypeScript SDK gate (`node`/`npm` unavailable);
    no `v0.1.0` tag was created (`docs/release/stable-status.md`).

## Iteration 20

- Added post-execution verification and controlled rollback.
- Added fail-closed release certification pipeline (`stable-gate.sh`).

## Iteration 17

- Added Manager Operations Console.
- Added system health/readiness view.
- Added profiles, tokens, policies, jobs, audit and fingerprints views.
- Added safe profile/token/policy status actions.
- Added explicit `aibridge_manage` permission boundary.
- Added manager security and observability documentation.

## [0.1.0] - Iteration 18

- Added Resource Explorer Manager UI.
- Added resource search/tree and Template/TV filters.
- Added Contract and QA inspection.
- Added fingerprint diff UI.
- Added safe preview and queued resource operations.
- Added Manager operation boundary and security tests documentation.

## Iteration 19
- Added approval workflow and durable change requests.
- Added human-in-the-loop approval gate and execution dispatch.
- Added workflow documentation and tests.
