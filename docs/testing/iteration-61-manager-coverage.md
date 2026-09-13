# Iteration 61 — Manager Coverage and Console/Rollback Fixes

## Scope

1. **`OperationsConsoleService` coverage and fix**: all eight console list methods silently ignored their
   `limit` (and the documented ordering) because the options array was passed as `getCollection()`'s cache
   flag. Covered by `OperationsConsoleServiceTest` and fixed.
2. **`ResourceRollbackProcessor` coverage and fix**: exceptions from `RollbackService` escaped
   `Processor::run()`; covered by `ResourceRollbackProcessorTest` and converted to JSON failures.
3. **`ResourceExplorerService::tree()` at `depth > 1`**: null-safe iteration plus tests that lock the depth
   nesting, ordering, per-level limit and soft-delete pruning.

## Design

### OperationsConsoleService

- **Defect.** Every list method called
  `getCollection($class, [], ['limit' => ..., 'sortby' => ..., 'sortdir' => ...])`. In xPDO the third
  argument is the **cache flag**, so the options were ignored: `OverviewProcessor` (`profiles(50)`,
  `tokens(50)`, `policies(50)`, `jobs(50)`, `audit(50)`, `fingerprints(50)`) returned unbounded rows in an
  arbitrary order (same defect class as Iteration 58).
- **Fix.** A private `query()` helper builds an `xPDOQuery` with `limit()` (clamped 1..500) and an optional
  `sortby()`; `jobs`, `audit`, `fingerprints` and `changes` use `created_at DESC`, `approvals` uses
  `requested_at DESC`. Iteration guards `?: []` were added. Field projection (including the deliberate
  absence of `token_hash`) is unchanged.

### ResourceRollbackProcessor

- **Defect.** `RollbackService::rollback()` throws `RuntimeException` for an unknown snapshot, invalid
  snapshot data and a missing target resource (automatic recreation is disabled). MODX 3.2.2
  `Processor::run()` does not catch exceptions from `process()`, so a manager call with a stale
  `snapshot_id` produced a connector error instead of a JSON failure.
- **Fix.** The rollback call is wrapped in `try/catch (\Throwable)` and returns
  `failure('Rollback failed.', ['code' => 'rollback_failed'])`; `snapshot_id < 1` returns
  `failure('snapshot_id is required.', ['code' => 'invalid_snapshot'])`. Exception messages are not echoed
  (no internals or secrets in responses). The success path and principal construction are unchanged.

### ResourceExplorerService::tree()

- **Hardening.** `foreach ($resources ?: [])` guards an empty/null collection.
- **Verified semantics** (documented in the method docblock and locked by tests): `$depth` is the number of
  nested `children` levels — `0` is a flat list, `1` adds one child level, `2` (the default used by the
  manager UI) adds a second; levels deeper than the first are capped at 100 items, `$limit` bounds each
  first-level call, every emitted node keeps `has_children`, and soft-deleted resources are never walked
  (a deleted node hides its subtree).

## Evidence

Local run against the Docker stack (MODX 3.2.2-pl, PHP 8.2.33), version identities at `0.9.1`:

```text
composer validate --no-check-publish --strict        valid
composer lint                                        PASS
composer verify-static-contract                      Static contract: PASS
MODX_ROOT=... vendor/bin/phpunit                     OK (158 tests, 6969 assertions)
  unit,contract,security                             OK (63 tests, 201 assertions)
  sdk                                                OK (11 tests, 52 assertions)
  integration                                        OK (84 tests, 6548 assertions)
bash scripts/sdk-typescript-check.sh                 TYPESCRIPT SDK: PASS (14 runtime tests)
bash scripts/ts-live-check.sh                        TYPESCRIPT SDK LIVE HTTP: PASS (15 tests)
bash scripts/verify-supply-chain-pins.sh             SUPPLY CHAIN PINS: PASS
Transport Package installation: PASS (Signature: aibridge-0.9.1)
PACKAGE REPRODUCIBILITY: PASS (aibridge-0.9.1, sha256 06e808433bad894dc89423fc50788a7ec1eb589a790fcc8365af5eab94377fac, 146755 bytes)

# new coverage
+ OperationsConsoleServiceTest::testLimitsAndOrderingAreAppliedToConsoleLists
+ OperationsConsoleServiceTest::testReadinessReportsExpectedChecks
+ ResourceRollbackProcessorTest (auth / permission / snapshot_id / unknown snapshot / restore + audit)
+ ResourceExplorerServiceTest::testTreeDepthExpandsNestedChildrenAndHidesDeletedSubtrees
```

## Compatibility and security

- Patch release: no scope, route, schema, migration or setting change, and no response-shape change. The
  console lists now honour the limits/order their signatures (and the overview processor) already documented.
- The processor fix is fail-closed: failures stay failures and are reported as JSON; exception text is not
  returned. Rollback still goes through `SecurityDecisionPipeline` (`resource.rollback`, manager channel)
  inside `RollbackService`; no policy or approval logic was touched.
- Additive machine codes `rollback_failed` / `invalid_snapshot` are the only contract additions.

## Certification

`Release` dispatch on `main`, commit `330b78a`, run `34773039854`: `verify` PASS (incl. `SUPPLY CHAIN PINS: PASS`),
`certify` PASS (`OK (158 tests, 923 assertions)`, `TYPESCRIPT SDK LIVE HTTP: PASS`,
`STABLE certification gates passed.`), `package` PASS. The CI artifact sha256
`06e808433bad894dc89423fc50788a7ec1eb589a790fcc8365af5eab94377fac` (146755 bytes) matches the local build.
Evidence artifact: `release-evidence-330b78acb15952209c56e9cbdd97b7119ed21c0d`.

## Status

Stable `0.9.1`, certified by run `34773039854`; tag `v0.9.1` and the GitHub Release are recorded in
`docs/release/0.9.1.md`.
