# Iteration 66 — Read-back privacy, deep-tree budget, supervised worker (`0.11.0`)

Base: Stable `0.10.0` (`main` = `f5fecc3`, tree clean). Release: `0.11.0` (minor: one additive setting,
one additive error code, queue worker behaviour).

Scope: close the three remaining hardening items from the `0.10.0` residual-risk list (read-back TV
redaction, deep `parent` traversal, hard job stop).

## 1. Changes

### Read-back TV redaction (`aibridge_redacted_tvs`)
- `Configuration/ConfigFactory` decodes `redacted_tvs` as JSON; `config/config.php` defaults it to `[]`.
- New system setting `aibridge_redacted_tvs` (textarea, `security` area, default `[]`) in
  `_build/elements/settings.php`.
- `Services/ResourceReadService::tvValues()` omits every TV whose name is listed, so the value never reaches
  the projection. Applies to all read-back surfaces that share the service: `GET /api/ai/v2/resources/{id}`,
  the MCP `resource_read` tool and the `modx://resource/{id}` resource template. The manager explorer
  (authenticated human path, `ResourceExplorerService`) is unchanged.
- The redacted-name list is read once per service instance from the decoded setting.

### Deep-tree traversal (`depth` up to 50 with a descendant budget)
- `ResourceReadService::LIST_DEPTH_MAX` raised from 10 to 50; `LIST_DESCENDANT_MAX = 5000` bounds the
  materialized id set.
- `descendantIds()` returns `null` on overflow; `list()` answers `400 too_many_descendants` instead of
  materializing an unbounded set. `depth` without `parent`, `0`, `> 50` or non-numeric still return
  `400 invalid_filter`; soft-deleted pruning and sibling isolation are unchanged.
- The descendant budget is constructor-injectable (default 5000) so the limit is testable without creating
  5000 rows.
- The MCP `resource_list` schema takes its `maximum` from `ResourceReadService::LIST_DEPTH_MAX` instead of a
  literal.

### Supervised worker hard timeout
- `Worker::runJob()` now holds the previous `runOnce()` body (handler, redaction, complete/fail, audit) and
  `runOnce()` is claim + `runJob()`.
- New `Worker::runOnceSupervised()`: after claiming, it spawns the runner (`[php, worker.php]`) through
  `proc_open` with `AIBRIDGE_EXEC_JOB=<id>` in the environment, polls it, and sends SIGKILL once the job's
  `timeout_seconds` budget is spent. The child initializes its own MODX/database connection, so the killed
  process cannot leave an open transaction: the server rolls it back.
- Hard-timeout outcome: the job is failed as **non-retryable** `job_timeout` (never requeued) and the
  `in_progress` idempotency key is abandoned (`IdempotencyService::abandon`), preserving the 0.10.0
  invariant that a timeout never replays a possibly-committed mutation while a pre-mutation abort stays
  retryable.
- A child that exits without settling the job is failed as retryable `job_failed`; child stderr is captured
  (bounded to 4 KiB), redacted with `SecretRedactor` and attached to the audit diagnostic. If `proc_open` is
  unavailable the worker falls back to inline execution.
- `worker.php` supervises by default; `--inline` restores the previous single-process cooperative mode. The
  child mode is driven by the `AIBRIDGE_EXEC_JOB` environment variable (no extra argv, so a runner command
  never has to accept arguments).

## 2. Migration implications

None. No schema or model change; the new setting defaults to `[]` (previous behaviour) and
`too_many_descendants` is an additive error code. Existing clients are unaffected unless the operator opts
into a redaction list or requests `depth > 10`.

## 3. Local verification (container `modx`, PHP 8.2.33, MODX 3.2.2-pl)

- `composer validate --no-check-publish --strict` — valid; `composer lint` — no syntax errors;
  `composer verify-static-contract` — PASS.
- `composer test -- --testsuite unit,contract,security` — **OK (71 tests, 226 assertions)**.
- `composer test -- --testsuite sdk` — OK (11 tests, 52 assertions).
- `MODX_ROOT=… composer test -- --testsuite integration` — **OK (108 tests, 10603 assertions)**, including
  `RestApiTest::testRedactedTemplateVariablesAreOmittedFromReadBack`,
  `RestApiTest::testResourceListSupportsDeepTreesWithinDescendantBudget` and the two
  `WorkerSupervisionTest` cases (hard timeout + key release, real child-process completion).
- `scripts/verify-package-reproducibility.php` — PASS (`aibridge-0.11.0`, sha256
  `1f23f9fd62df99d26a0b923301dac17010aa25ee6b9f240774dda4096d9e0bb2`, 148228 bytes).
- Package built and installed on the disposable stack (`Transport Package installation: PASS`,
  `Signature: aibridge-0.11.0`), `docker compose restart modx`, `/health` reports `0.11.0`.
- `sdk/typescript` runtime 14/14 after `npm run build`; `scripts/ts-live-check.sh` —
  `TYPESCRIPT SDK LIVE HTTP: PASS` (15/15, health and MCP `serverInfo` report `0.11.0`).
- CI certification: dispatch `34973881413` (`main`, `452f1db`) — `verify` PASS (incl.
  `SUPPLY CHAIN PINS: PASS`), `certify` PASS, `package` PASS; `OK (190 tests, 1219 assertions)`,
  `TYPESCRIPT SDK LIVE HTTP: PASS`, `PACKAGE REPRODUCIBILITY: PASS` with the same sha256
  `1f23f9fd…`, `STABLE certification gates passed.` Push-CI on `452f1db`: `Quality Gates` `34973874041`,
  `MODX Integration` `34973873975` — success. Evidence artifact
  `release-evidence-452f1db38f11d11eac02bf732195588b4a6fdace` (90 days).

## 4. Risks / notes

- Hard kill window: if SIGKILL lands after the mutation transaction committed but before the job reaches a
  terminal state, the job is recorded as `job_timeout` although the mutation was applied, and the key is
  released as for any timeout. The window is narrow (the kill fires at the budget) and the job is never
  requeued; `--inline` remains fully cooperative.
- The descendant budget (5000 ids) means very large subtrees must still be walked level by level with
  `parent`; the error is explicit (`too_many_descendants`).
- Redaction omits the key from the `tvs` map rather than returning `null`, so a redacted TV is
  indistinguishable from a TV that is not bound to the template; TV names themselves remain discoverable
  through `site_schema`. The list is site-wide, not per-token.
