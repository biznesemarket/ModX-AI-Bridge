# Changelog

## 0.10.0 — 2026-09-13

- Security (info disclosure): client-facing errors no longer carry raw exception text. `RestApi`,
  `ResourceExecutionService` (`resource.preview`, `resource.execute`) and the queue worker return stable
  messages (`preview_failed`, `execution_failed`, `job_failed`, `job_timeout`) while the full diagnostic is
  redacted with `SecretRedactor` and written only to the audit trail / MODX log. Manager workflow failures
  return a generic `workflow_error` instead of `$e->getMessage()`.
- Security (input bounds): `Idempotency-Key` is capped at 190 characters (the column width) at the REST
  boundary and in the execution service, returning `400 idempotency_key_invalid` instead of a database 500;
  the MCP resource tool schemas document the same `maxLength`.
- Schema: the `aibridge_idempotency` and `aibridge_rate_limits` unique indexes now start with `profile_id`
  (`profile_id,idempotency_key,principal_id,operation` and `profile_id,bucket_key,window_start`), so two
  profiles sharing a principal can no longer collide (request corruption/500) and the rate-limit lookup reads
  the caller's own bucket. Migration `003_profile_scoped_unique_indexes.sql` upgrades existing installations;
  fresh installs get the indexes from the xPDO resolver. `php scripts/migrations/migrate.php migrate` applies it.
- Queue: job timeouts are terminal. `JobTimeoutException` extends `NonRetryableJobException`, so a job that
  overran its budget is never requeued (previously a timer that fired after a committed mutation could replay
  it). A per-attempt `JobDeadline` is checked cooperatively by the handler and by the execution service right
  before the mutation transaction (`execution_timeout`); that abort releases the idempotency key (nothing was
  applied) so the request stays retryable, and the queue handler records it as a non-retryable `job_timeout`
  instead of a completed job.
- Performance: `CacheInvalidationService::invalidateResource()` deletes only the affected resource's page
  cache and the owning context's cached map entry (lazy regeneration on the next request) instead of
  refreshing the whole MODX `db` partition; invalidation runs after the write transaction commits.
- Operations console: `OperationsConsoleService::changes()` / `approvals()` return explicit projections
  (JSON fields decoded, `profile_id`/ids typed) instead of raw `toArray()` rows; the manager
  `WorkflowProcessor` list modes delegate to the same projections (one shape); the service constructor uses
  `readonly`.
- Tech debt: removed the unreferenced stub classes (`Validators/RequestValidator`, `Middleware/*`,
  `Services/AssetService`, `Services/SchemaService`, the `src/Processors/*` resource/site stubs). The
  `static-contract` gate now fails if they are reintroduced.
- Minor release: adds a schema migration and an additive error code; existing routes, settings and
  operations are unchanged. SDK `User-Agent` moves to `0.10`; `clientInfo`/package versions to `0.10.0`.
- Tests: `ProfileScopedStorageTest` (profile-scoped rate limits, idempotency keys incl. abandon/retry,
  scoped cache invalidation), `ErrorDisclosureTest` (raw-message regression guard),
  `ResourceMutationE2ETest` deadline-abort-retry and handler fail-fast cases, REST over-long and
  multibyte-boundary idempotency-key cases, console projection assertions, and `JobDeadline`/terminal-timeout
  unit cases.

## 0.9.3 — 2026-09-13

- Security: the publish approval is now bound to the caller. `SecurityDecisionPipeline::isApprovedForChange()`
  verifies the referenced `ChangeRequest` is a `resource.publish` change, belongs to the caller's profile
  (when the principal is profile-scoped) and targets the same resource as the request. Previously any approved
  `Approval` row could authorize publishing an arbitrary resource — including one from another profile — and
  could be replayed. All request paths now pass the target `resource_id` into the pipeline.
- Fixed the manager-connector MCP surface: the trusted `channel` is propagated from the MCP transport into the
  tool-handler requests, so manager-channel mutations are accepted by the execution-layer pipeline instead of
  being rejected `ip_not_allowed` (the previous behavior failed closed; no privilege change).
- Fixed the workflow verification mismatch for `published`: `ChangeRequestService::create()` drops `published`
  for create/update, so the recorded `after_json` matches what execution applies and the change no longer
  fails post-execution verification after committing the other fields.
- The `resource_publish` MCP tool now declares `change_id` as required, matching the enforced pipeline
  contract.
- Docs: corrected the SDK identity note (`clientInfo` bumps to `0.9.3`; the SDK `User-Agent` stays `0.9` on
  patches) and the `install-package.php` examples (full path/`.transport.zip`).
- Tests: new cross-profile and wrong-resource publish rejection cases in `McpPublishApprovalTest`; the new
  integration tests now clean up their fixtures (`tearDownAfterClass`).
- Patch release: security and correctness fixes; no new scope, route, schema, migration or setting.

## 0.9.2 — 2026-09-13

- Security: publishing can no longer be performed through `resource.update`/`resource.create`. `published`
  was accepted by the writable-field whitelist, so any principal with `resource:write` could set
  `published = 1` and bypass the `resource.publish` approval gate. Publication now happens only through the
  approval-gated `resource.publish` operation.
- Fixed non-deterministic site discovery: `TemplateInspector`, `TVInspector`, `ChunkInspector` and
  `SnippetInspector` passed their `sortby` options as `getCollection()`'s cache flag, so collections were
  returned in storage order and the site fingerprint could change without any content change. They now build
  an `xPDOQuery` with an explicit `sortby('name'|'templatename', 'ASC')`.
- Fixed `WorkflowProcessor::listChanges()/listApprovals()`: `limit`/`sortby` were passed as the cache flag and
  ignored; both lists now use an explicit query (200 rows, `created_at`/`requested_at` DESC).
- Hardened `IpAllowlist`: malformed CIDR prefix lengths (`/33` on IPv4, `/129` on IPv6, negative or
  non-numeric) previously matched every address; they are now rejected (fail closed).
- Hardened the manager-connector MCP surface (`processors/mcp.class.php`): it now requires manager
  authentication and the `aibridge_manage` permission, derives the principal from the authenticated MODX user
  and the real transport peer, and never trusts request-supplied `scopes` or `_client_ip`.
- MCP `resource_publish` now forwards `approval_id`/`change_id` into the security pipeline, so a valid
  approved change request can publish through MCP (previously always denied when approvals are required).
- New coverage: `SiteDiscoveryDeterminismTest`, `McpPublishApprovalTest`,
  `ResourceMutationE2ETest::testUpdateCannotBypassPublishApproval` and
  `IpAllowlistTest::testRejectsMalformedPrefixLengths`.
- Patch release: security and correctness fixes; no new scope, route, schema, migration or setting.

## 0.9.1 — 2026-09-13

- Fixed `OperationsConsoleService` list methods: `limit`/`sortby` were passed as `getCollection()`'s cache flag
  and silently ignored, so the Operations Console overview returned unbounded rows in arbitrary order. All
  lists now build an `xPDOQuery` with `limit()` (1..500); `jobs`, `audit`, `fingerprints` and `changes` are
  ordered by `created_at DESC` and `approvals` by `requested_at DESC`.
- Fixed `ResourceRollbackProcessor`: `RollbackService` exceptions (unknown/invalid snapshot, missing target
  resource) escaped `Processor::run()` and surfaced as connector errors. The processor now returns a JSON
  failure (`rollback_failed`); a missing `snapshot_id` reports `invalid_snapshot`.
- Hardened `ResourceExplorerService::tree()` against empty collections and documented the depth semantics
  (0 = flat list; each level adds one nested `children` level; deeper levels are capped at 100 items).
- New integration coverage: `OperationsConsoleServiceTest`, `ResourceRollbackProcessorTest` and
  `ResourceExplorerServiceTest::testTreeDepthExpandsNestedChildrenAndHidesDeletedSubtrees`.
- Patch release: bug fixes and tests only, no scope, route, schema, migration or setting change.

## 0.9.0 — 2026-09-13

- Recursive `parent` list filter: `GET /api/ai/v2/resources` and the `resource_list` MCP tool accept an
  optional `depth` (1..10, default 1) alongside `parent`. `depth = 1` keeps the historical direct-children
  behaviour; higher values include descendants down to that many tree levels. Soft-deleted resources are
  skipped while walking, so a soft-deleted node hides its subtree. `depth` without `parent`, or a value
  outside 1..10, returns `400 invalid_filter`.
- `parent` is now a supported `sort` column.
- Additive: no new scope, route, schema, migration or setting; the response shape is unchanged.

## 0.8.0 — 2026-09-13

- Site-schema resource filters: `GET /api/ai/v2/site/schema` (and the `site_schema` MCP tool) accept
  `context_key` and `template_id`, and the embedded resource list now honours `limit` (previously the options
  were passed as `getCollection()`'s cache flag and ignored). The response shape is unchanged.
- Manager-surface integration coverage: `AdminProcessor` permission guard (all three branches) and
  `ResourceExplorerService::get/contract/qa/fingerprintDiff`.
- `publishedon` in the resource-list summary is now locked by an assertion (already present since 0.4.0).
- Additive: no new scope, schema, migration or setting change.

## 0.7.1 — 2026-09-13

- Fixed `ResourceExplorerService::search()`: the flat `OR:` criteria keys OR-ed the whole clause (a search
  matched almost every resource) and the query options were passed as `getCollection()`'s cache flag, so
  `limit`/`sortby` were ignored. It now uses a grouped OR search and an explicit `newQuery()` with
  `limit()`/`sortby()`; the TV filter is a join (no `contentid` materialization).
- Fixed `ResourceExplorerService::tree()` and `RestApi::profiles()`: `limit`/`sortby` were silently ignored
  (passed as the cache flag); both now build a `newQuery()` and honour their documented limits/order.
- Patch release: bug fixes only, no scope/route/schema/setting change and no response-shape change.
- New integration coverage: `tests/Integration/ResourceExplorerServiceTest.php`.

## 0.7.0 — 2026-09-13

- `/capabilities` now advertises the available MODX contexts (`key`, `name`, `description`), so clients know the
  valid `context_key` filter values.
- The resource-list TV filter is join-based: it matches an explicit `modTemplateVarResource` value through a
  join instead of materializing `contentid`s and applying `id:IN`, so `count`/`total` stay a single aggregate
  query. Public filter contract unchanged.
- `publishedon` is confirmed as a supported `sort` column and is now covered by an integration test.
- Additive: no new scope, schema, migration or setting; the resource list response is unchanged.

## 0.6.0 — 2026-09-13

- Template-variable filter for the resource list: `GET /api/ai/v2/resources` and the `resource_list` MCP tool
  accept `tv_name` (resources with an explicit value for that TV) and optional `tv_value` (LIKE match).
  `tv_value` without `tv_name`, or an unknown `tv_name`, returns `400 invalid_filter`; a known TV with no
  matches returns an empty result. Values are trimmed, length-capped and LIKE-escaped.
- Additive: two new query parameters on the existing `resource.list` / `resource:read` operation; no new scope,
  schema, migration or setting. Only explicit per-resource TV overrides are matched (not TV `default_text`).
- xPDO note: avoid partial `select()` on collection queries (missing PK triggers an unfiltered lazy load and
  memory exhaustion) — see `docs/testing/iteration-56-list-tv-filter.md`.

## 0.5.0 — 2026-09-13

- MCP resource template `modx://resource/{id}`: `resources/read` now resolves URI templates (extracting `id`)
  and returns the read-back projection for one persisted resource, under the existing `resource.read` /
  `resource:read` operation. A missing or soft-deleted resource returns JSON-RPC `-32002 Resource not found`
  (`reason: resource_not_found`).
- New MCP method `resources/templates/list` (advertises `uriTemplate` entries) and `resourceTemplates()` in
  the PHP/TypeScript MCP clients; `McpServer::capabilities()` exposes the templates.
- Additive: no REST route, scope, schema, migration or setting change; concrete resources such as
  `modx://site/schema` behave exactly as before.

## 0.4.0 — 2026-09-13

- Filtered, paginated resource list: `GET /api/ai/v2/resources` (operation `resource.list`, scope
  `resource:read`) returns a summary projection plus `count`, `total`, `limit` and `offset`. Whitelisted
  filters: `parent`, `template`, `context_key`, `published`, `q` (pagetitle/alias/description match), `limit`
  (1..100, default 25), `offset`, `sort`, `dir`; invalid values return `400 invalid_filter`. Also exposed as
  the `resource_list` MCP tool, `listResources()` in both SDKs and a `resource.list` capability entry.
- Additive: no new scope, schema, migration, setting or mutation; the list projection excludes `content` and
  TVs. xPDO note: query options go through `newQuery()` (`limit`/`sortby`), and the LIKE search uses a grouped
  OR condition — see `docs/testing/iteration-54-resource-list.md`.

## 0.3.0 — 2026-09-13

- Read-back now includes template variables: `GET /api/ai/v2/resources/{id}` and the `resource_read` MCP tool
  return a `tvs` map (TV name -> `string|null`; structured TV values are JSON-encoded) for every TV bound to the
  resource template. Additive: the same `resource.read` operation/`resource:read` scope, no schema or migration,
  no new route or setting; a resource without TVs returns `tvs: {}`.
- `resource.read` is now listed in the capability catalog (`/capabilities`). The TypeScript SDK types the read
  projection (`ResourceProjection`/`ResourceTvValues`/`ResourceReadResponse`); both SDK `User-Agent`s and the MCP
  client `initialize` version move to `0.3`.

## 0.2.0 — 2026-09-13

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
