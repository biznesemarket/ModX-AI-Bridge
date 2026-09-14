# Iteration 65 — Audit backlog closure (`0.10.0`)

Base: Stable `0.9.3` (`main` = `ccb4dd0`, tree clean). Release: `0.10.0` (minor: schema migration +
additive error contract).

Scope: `docs/ai-agent/baseline/known-defects.md` items **36–43** (all open audit-backlog items).

## 1. Changes

### #36 — info disclosure in errors
- `Execution/ResourceExecutionService`: `resource.preview` returns `preview_failed` / `Resource preview
  failed.`; the mutation catch returns `execution_failed` / `Resource execution failed.`. The raw
  `$e->getMessage()` is passed through `SecretRedactor::redactText()` and recorded only in the audit event /
  MODX log.
- `Queue/Worker`: job errors are stable (`job_failed` / `Job execution failed.`, `job_timeout` / `Job
  exceeded its time budget.`); the redacted diagnostic is written to the audit `job_failed` event and never to
  `error_json`.
- `processors/manager/workflow.class.php`: `workflow_error` failures return `Workflow operation failed.`;
  the redacted cause goes to the MODX log.

### #37 — profile-scoped storage
- `schema/aibridge.mysql.schema.xml`: `idempotency_unique` → (`profile_id`,`idempotency_key`,`principal_id`,
  `operation`); `bucket_unique` → (`profile_id`,`bucket_key`,`window_start`). Model regenerated with
  `scripts/generate-schema.php` (+ `verify-generated-model`).
- `Security/RateLimiter`: the read-back `SELECT` now filters on `profile_id` (the `ON DUPLICATE KEY` insert
  already wrote it).
- `migrations/001_initial.sql` mirrors the new indexes; `migrations/003_profile_scoped_unique_indexes.sql`
  upgrades existing installations (drop + re-add the two indexes; safe on a resolver-created fresh install).
- Verified on the upgraded disposable stack: `migrate.php migrate` applied `003`, and `SHOW INDEX` confirms
  `profile_id` is column 1 of both unique indexes.

### #38 — bounded idempotency key
- `Execution/ResourceExecutionService::MAX_IDEMPOTENCY_KEY_LENGTH = 190`; the execution service rejects
  over-long keys with `idempotency_key_invalid` before persistence/queueing.
- `Api/RestApi::mutate()` rejects `Idempotency-Key` > 190 chars with `400 idempotency_key_invalid` (previously
  the `varchar(190)` insert raised a 500).
- MCP resource tool schemas declare `maxLength: 190`.

### #39 — dead stub removal
- Removed: `Validators/RequestValidator`, `Middleware/{Authentication,Authorization,RateLimit}Middleware`,
  `Services/{Asset,Schema}Service`, `Processors/{ResourceCreate,ResourceUpdate,ResourceDelete,
  ResourcePreview,ResourcePublish,ResourceValidate,SiteSchema,SiteFingerprint}Processor`.
- Retained deliberately: `Processors/ResourceRollbackProcessor` (live manager path, covered by
  `ResourceRollbackProcessorTest`), `JobStatusProcessor` and `ChangeVerificationProcessor` (behaviour-bearing
  processors, invoked by class name through the MODX processor boundary). The defect only listed the
  placeholder stubs.
- `tests/Integration/static-contract.php` now fails if any removed file reappears or if
  `CacheInvalidationService` uses a whole-`db` refresh again.

### #40 — worker timeout semantics
- `JobTimeoutException` extends `NonRetryableJobException` (base class no longer `final`): a timed-out job is
  never requeued, so a timer that fires after a committed mutation cannot replay it.
- New `Queue/JobDeadline` value object; `Worker` builds one per attempt from the job's `timeout_seconds` and
  passes it to `JobContext`.
- `JobContext::ensureWithinDeadline()` throws `JobTimeoutException` when the budget is spent;
  `ResourceExecutionJobHandler` checks before dispatch and forwards the deadline as `_deadline_at`;
  `ResourceExecutionService::mutate()` re-checks right before the snapshot/transaction and returns
  `execution_timeout` without touching data. That abort calls `IdempotencyService::abandon()` (nothing was
  applied), so the same key can be retried instead of replaying the timeout forever (the manager path uses a
  deterministic key); the handler converts the result into a non-retryable `JobTimeoutException` so the job is
  recorded as `job_timeout`/failed rather than completed.

### #41 — scoped cache invalidation
- `CacheInvalidationService::invalidateResource(int $resourceId, ?modResource $resource = null)`:
  `modResource::clearCache($contextKey)` deletes only the resource page cache and the owning context's cached
  map entry is deleted via `cacheManager->delete($context->getCacheKey(), [xPDO::OPT_CACHE_KEY => …])`, so it
  regenerates lazily on the next request instead of an eager whole-context rebuild. The previous whole-`db`
  refresh is gone; the caller passes the live resource object so a delete keeps its context after the row is
  removed, and invalidation runs after the transaction commits. Unresolvable ids return
  `{refreshed:false,warning:resource_not_found}` (fail-closed, no cache-wide flush).

### #42/#43 — console projections and style
- `OperationsConsoleService::changes()` / `approvals()` return explicit projections (`input`/`before`/
  `after`/`diff`/`qa` decoded from JSON; ids typed; `profile_id`) instead of raw `toArray()`.
- `WorkflowProcessor::listChanges()/listApprovals()` delegate to the same console projections, so the manager
  processor and the console expose one shape (the manager list response changes from raw rows to the
  projection).
- Constructor is `private readonly modX $modx`.

## 2. Migration implications

- `003_profile_scoped_unique_indexes.sql` is an index-only change (drop + re-add with the same names) and is
  safe to apply on a resolver-created fresh install. The new keys are strict supersets of the old ones
  (`profile_id` prepended), so the constraint is more permissive and the `ADD UNIQUE KEY` cannot fail on
  existing rows; no cross-profile duplicate pre-check is needed.
- The statements rebuild both indexes over all existing idempotency/rate-limit rows and take a metadata lock,
  and MySQL DDL auto-commits, so apply it in a maintenance window on a long-lived busy database. If the second
  statement fails the runner exits non-zero and does not record the file; a rerun is idempotent.
- Fresh installs receive the indexes from the xPDO resolver.

## 3. Local verification (container `modx`, PHP 8.2.33, MODX 3.2.2-pl)

- `composer lint` — no syntax errors.
- `composer validate --no-check-publish --strict` — valid.
- `composer verify-static-contract` — PASS.
- `composer verify-generated-model` — PASS (model regenerated twice, deterministic).
- `composer test -- --testsuite unit,contract,security` — **OK (71 tests, 226 assertions)**.
- `composer test -- --testsuite sdk` — OK (11 tests, 52 assertions).
- `MODX_ROOT=… composer test -- --testsuite integration` — **OK (104 tests, 8584 assertions)**.
- `scripts/verify-package-reproducibility.php` — PASS (`aibridge-0.10.0`, sha256
  `4ca7d0a8f2b7e97066833d6eedafb174c6e5c2772bef9bb8c97543acee4227bf`, 144514 bytes; matching the installed
  `/health` version `0.10.0`).
- `bash scripts/sdk-typescript-check.sh` (host Node 24) — 14/14 runtime tests, `TYPESCRIPT SDK: PASS`.
- Runtime: package built and installed on the disposable stack (`Transport Package installation: PASS`),
  `docker compose restart modx`, migration `003` applied against the pre-existing database, `SHOW INDEX`
  verified. Full CI certification (live HTTP E2E, runtime verification, reproducibility on the runner) is
  pending the release dispatch.

## 4. Risks / notes

- The timeout fix is cooperative within the current execution model: a single long-running SQL statement is
  still not preempted, but the job no longer replays after a timeout and the mutation boundary refuses to
  start once the budget is spent.
- Error-message sanitisation intentionally drops operator-visible detail from HTTP/MCP responses; the redacted
  diagnostic remains in the audit trail (`resource_execution_failed`, `job_failed`) and the MODX log.
- `ErrorDisclosureTest` scans source text for the removed patterns; it guards regressions but is not a runtime
  capture.
