# Iteration 58 — Manager-Layer xPDO Fixes (Search, Tree, Profiles)

## Scope

Fixes the latent xPDO issues identified in Iterations 54/57 but deliberately left outside the read-back scope:

1. `ResourceExplorerService::search()` used flat `OR:` criteria keys (which OR the whole clause, including
   `deleted = 0`, so a search matched almost every resource) and passed query options as the `getCollection()`
   `$cacheFlag` argument (so `limit`/`sortby` were silently ignored).
2. `ResourceExplorerService::tree()` passed `limit`/`sortby` as the `$cacheFlag` argument, so the manager tree
   was not limited or ordered by `menuindex`.
3. `RestApi::profiles()` passed `limit`/`sortby` as the `$cacheFlag` argument, so the advertised 100-row cap
   and id ordering were not applied.

The fixes use the same patterns as Iteration 54: an explicit `newQuery()` with `limit()`/`sortby()` and a grouped
`where([...], xPDOQuery::SQL_OR)` search. The TV filter in `search()` is now a join on `modTemplateVarResource`
instead of collecting `contentid`s, matching the read-back implementation.

## Design

- **`search()`** builds one `newQuery()`: `deleted = 0`, an optional grouped LIKE search over
  pagetitle/alias/description, optional `template`, optional TV join (`tv_name`/`tv_value`), then
  `limit($limit)->sortby('modResource.editedon','DESC')`. LIKE input escapes `\`, `%` and `_`. The dead
  `aibridge_search_escape` line was removed (the value was overwritten before use).
- **`tree()`** builds one `newQuery()` with `parent`/`deleted`, `limit($limit)` and
  `sortby('modResource.menuindex','ASC')`.
- **`profiles()`** builds one `newQuery()` with the optional profile filter, `limit(100)` and
  `sortby('id','ASC')`.
- Public signatures, return shapes and the maximum limits are unchanged; only the previously ignored options
  now take effect.

## Evidence

Local run against the Docker stack (MODX 3.2.2-pl, PHP 8.2.33), version identities at `0.7.1`:

```text
composer validate --no-check-publish --strict        valid
composer lint                                        PASS
composer verify-static-contract                      Static contract: PASS
composer test -- --testsuite unit,contract,security  OK (63 tests, 201 assertions)
composer test -- --testsuite sdk                     OK (11 tests, 52 assertions)
bash scripts/sdk-typescript-check.sh                 TYPESCRIPT SDK: PASS (14 runtime tests)
MODX_ROOT=... vendor/bin/phpunit --testsuite integration   OK (68 tests, 4911 assertions)
bash scripts/ts-live-check.sh                        TYPESCRIPT SDK LIVE HTTP: PASS (15 tests)
bash scripts/verify-supply-chain-pins.sh             SUPPLY CHAIN PINS: PASS

# container: rebuild + install, restart for opcache
Transport Package installation: PASS (Signature: aibridge-0.7.1)
curl /api/ai/v2/health                               {"status":"ok","version":"0.7.1",...}
MODX runtime verification: PASS
PACKAGE REPRODUCIBILITY: PASS (aibridge-0.7.1, sha256 f9ee3089407beaa9be629407c3ffc6121c01828e85e03f9adc4a151e584a1786, 145574 bytes)

# new coverage
+ ResourceExplorerServiceTest::testSearchReturnsOnlyMatchingResources   (regression: no OR-match-all)
+ ResourceExplorerServiceTest::testSearchAppliesLimitAndTemplateFilter  (limit + template + unknown TV)
+ ResourceExplorerServiceTest::testTreeAppliesLimitAndSortsByMenuindex  (limit + menuindex sort)
```

Test-robustness fix found during local verification: the live TV-filter test used a fixed `tv_value`, so in the
persistent stack the new resource could fall outside `limit: 5` as earlier runs accumulated matches. The live
test now uses a per-run unique TV value (CI runs on a fresh stack, so this was latent).

## Compatibility and security

- Behaviour fix, no contract change: `search()` now returns only matches (previously everything), and
  `tree()`/`profiles()` now honour their documented limits. Response shapes are unchanged.
- Read-only manager/REST read paths; no scope, schema, migration or setting change and no mutation path
  touched. The manager surface still runs through the existing authorization and profile isolation.

## Status

Implementation complete and locally verified; release target `0.7.1` (patch: bug fixes only). Next, per the
established flow: bump all identity files -> full CI gate -> evidence/tag -> tag-run -> GitHub Release.
