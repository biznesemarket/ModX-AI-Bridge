# Iteration 59 — Manager Coverage and Site-Schema Resource Filters

## Scope

1. **Integration coverage for the manager surface** requested in HANDOFF §6:
   `ResourceExplorerService::get/contract/qa/fingerprintDiff` and the `AdminProcessor` permission guard.
2. **Site-schema resource filters**: `discover()` now filters the embedded resource list by `context_key`
   and `template_id`, and the list `limit` is actually applied (previously passed as the `getCollection()`
   cache flag and ignored).
3. **`publishedon` in the list summary** was already present since Iteration 54; an explicit assertion now
   locks it.

## Design

- **`AdminProcessorGuardTest`** (integration): builds a runtime anonymous `modX` double with an overridable
  `hasPermission()` and a duck-typed authenticated user, plus an anonymous `AdminProcessor` probe exposing
  `requirePermission()` and `console()`. Covers all three branches: unauthenticated →
  `manager_auth_required`; authenticated without permission → `manager_permission_required`; authenticated with
  permission → `null` and an `OperationsConsoleService`.
- **`SiteIntelligenceService::resources()`** now builds one `newQuery()` with optional `parent` (`root_id`),
  `context_key` and `template`, applies `limit()` and `sortby('modResource.id','ASC')`, and keeps the existing
  output shape. `discover()` reads the new `input` keys; invalid (too long) context keys are ignored.
- **MCP/OpenAPI**: `site_schema` advertises `context_key`/`template_id`; `/site/schema` documents them.
- **Explorer tests** added for `get` (content/TVs, missing, soft-deleted), `contract`/`qa` (shape, missing
  resource) and `fingerprintDiff` (changed/added paths, missing ids, invalid snapshot JSON).

## Evidence

Local run against the Docker stack (MODX 3.2.2-pl, PHP 8.2.33), version identities at `0.8.0`:

```text
composer validate --no-check-publish --strict        valid
composer lint                                        PASS
composer verify-static-contract                      Static contract: PASS
composer test -- --testsuite unit,contract,security  OK (63 tests, 201 assertions)
composer test -- --testsuite sdk                     OK (11 tests, 52 assertions)
bash scripts/sdk-typescript-check.sh                 TYPESCRIPT SDK: PASS (14 runtime tests)
MODX_ROOT=... vendor/bin/phpunit --testsuite integration   OK (75 tests, 5320 assertions)
bash scripts/ts-live-check.sh                        TYPESCRIPT SDK LIVE HTTP: PASS (15 tests)
bash scripts/verify-supply-chain-pins.sh             SUPPLY CHAIN PINS: PASS

# container: rebuild + install, restart for opcache
Transport Package installation: PASS (Signature: aibridge-0.8.0)
curl /api/ai/v2/health                               {"status":"ok","version":"0.8.0",...}
MODX runtime verification: PASS
PACKAGE REPRODUCIBILITY: PASS (aibridge-0.8.0, sha256 c0b96adb89c991a9c30b2164fd4ca9b18d4fb7169020b6f7cd7534e573ae2599, 145761 bytes)

# new coverage
+ AdminProcessorGuardTest (3 branches of the manager permission guard)
+ ResourceExplorerServiceTest::testGetReturnsDetailsAndTemplateVariables
+ ResourceExplorerServiceTest::testContractAndQa
+ ResourceExplorerServiceTest::testFingerprintDiffReportsChanges
+ RestApiTest::testSiteSchemaFiltersResources      (template_id/context_key/limit)
+ RestApiTest::testResourceListFiltersAndPagination (publishedon in the summary)
+ TS live: site schema filters (REST + MCP site_schema)
```

## Compatibility and security

- Additive read-only change: two new optional query parameters on the existing `site.schema` operation and a
  limit fix; existing calls without filters behave as before (the response shape is unchanged).
- `AdminProcessor` behaviour is unchanged; the new tests assert the existing guard. No scope, schema, migration
  or setting change.

## Certification

`Release` dispatch on `main`, commit `d49c044`, run `34769066811`: `verify` PASS (incl. `SUPPLY CHAIN PINS: PASS`),
`certify` PASS (`OK (149 tests, 783 assertions)`, `TYPESCRIPT SDK LIVE HTTP: PASS`,
`STABLE certification gates passed.`), `package` PASS. The CI artifact sha256 `c0b96adb…` (145761 bytes) matches
the local build. Evidence artifact: `release-evidence-d49c04428824201573e248c0c87593a5417facd3`.

## Status

Stable `0.8.0`, certified by run `34769066811`; tag `v0.8.0` and the GitHub Release are recorded in
`docs/release/0.8.0.md`.
