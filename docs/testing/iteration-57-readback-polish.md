# Iteration 57 — Read-Back Polish: Contexts, TV-Filter Join, Sort Coverage

## Scope

Closes the remaining read-back candidates from HANDOFF §6:

1. **`publishedon` sort** — already supported since the list was added (`SORTABLE` in Iteration 54 and the
   MCP/OpenAPI enums include it); this iteration adds explicit integration coverage so the contract is locked.
2. **Contexts in `/capabilities`** — the manifest now advertises the available MODX contexts (`key`, `name`,
   `description`) so clients know the valid `context_key` filter values.
3. **`total` / TV-filter optimization** — the TV filter no longer materializes matching `contentid`s and uses
   `id:IN`; it is applied as a join on `modTemplateVarResource`, so `count`/`total` remain a single aggregate
   query with no id list in memory.

## Design

- **`CapabilityService`** now accepts an optional `modX`; when present, `manifest()` adds `contexts`. The
  application passes the container (`new CapabilityService(modx: $this->modx)`); the class stays usable without
  a container (unit tests) and simply omits `contexts`.
- **`ResourceReadService::listQuery()`** takes the resolved `tv_id` and optional LIKE value and adds
  `innerJoin(modTemplateVarResource, 'aibridge_tv', 'modResource.id = aibridge_tv.contentid')`. There is at most
  one TV value row per resource, so no `DISTINCT` is required. Sort columns are qualified as
  `modResource.<column>` because the joined table also has an `id` column.
- No new operation, scope, route or parameter; the public filter contract is unchanged.

## xPDO findings (important)

- `getCollection(modContext::class, $xPDOQuery)` returned an empty collection even though the table has rows
  and `getCount(modContext::class)` is 2; the same plain `getCollection(modContext::class)` returns both
  contexts. The service uses the plain collection and sorts in PHP (contexts are few). Treat `modContext`
  collection queries with a criteria object as unreliable.
- Joins are a workable alternative to id materialization, but any unqualified `id` in `sortby`/`where` becomes
  ambiguous once `modTemplateVarResource` (which also has `id`) is joined — qualify with the resource alias.

## Evidence

Local run against the Docker stack (MODX 3.2.2-pl, PHP 8.2.33), version identities at `0.7.0`:

```text
composer validate --no-check-publish --strict        valid
composer lint                                        PASS
composer verify-static-contract                      Static contract: PASS
composer test -- --testsuite unit,contract,security  OK (63 tests, 201 assertions)
composer test -- --testsuite sdk                     OK (11 tests, 52 assertions)
bash scripts/sdk-typescript-check.sh                 TYPESCRIPT SDK: PASS (14 runtime tests)
MODX_ROOT=... vendor/bin/phpunit --testsuite integration   OK (65 tests, 4555 assertions)
bash scripts/ts-live-check.sh                        TYPESCRIPT SDK LIVE HTTP: PASS (15 tests)
bash scripts/verify-supply-chain-pins.sh             SUPPLY CHAIN PINS: PASS

# container: rebuild + install, restart for opcache
Transport Package installation: PASS (Signature: aibridge-0.7.0)
curl /api/ai/v2/health                               {"status":"ok","version":"0.7.0",...}
MODX runtime verification: PASS
PACKAGE REPRODUCIBILITY: PASS (aibridge-0.7.0, sha256 63df401306df5765c40f4a055664b08ac9239108f378f23ac75a17398f0c84a7, 145536 bytes)

# new/changed coverage
+ RestApiTest::testCapabilitiesWithScopedToken  (contexts include web/mgr)
+ RestApiTest::testResourceListFiltersAndPagination  (sort=publishedon&dir=desc)
+ RestApiTest::testResourceListFiltersByTemplateVariable  (join-based tv filter unchanged contract)
+ TS live: capabilities exposes contexts
```

## Compatibility and security

- Additive: `/capabilities` gains a `contexts` array (new field; clients ignore unknown fields) and the list
  query strategy changes internally with an identical response. No scope, schema, migration or setting change.
- The TV join runs inside the same `resource.list` / `resource:read` operation and remains read-only; the
  summary projection still excludes `content`/TVs.

## Certification

`Release` dispatch on `main`, commit `82e9917`, run `34764596898`. The first attempt failed at step 3 because
the MODX entrypoint did not create `/var/www/html/.modx-ready` within the timeout — an infrastructure flake
before this code was installed; `gh run rerun 34764596898 --failed` then passed: `verify` PASS (incl.
`SUPPLY CHAIN PINS: PASS`), `certify` PASS (`OK (139 tests, 736 assertions)`, `TYPESCRIPT SDK LIVE HTTP: PASS`,
`STABLE certification gates passed.`), `package` PASS. The CI artifact sha256 `63df4013…` (145536 bytes) matches
the local build. Evidence artifact: `release-evidence-82e9917ddd6f10a77ab9d7468435deb085fedbaa`.

## Status

Stable `0.7.0`, certified by run `34764596898` (rerun); tag `v0.7.0` and the GitHub Release are recorded in
`docs/release/0.7.0.md`.
