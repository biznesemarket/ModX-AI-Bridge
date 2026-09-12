# Changelog

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
