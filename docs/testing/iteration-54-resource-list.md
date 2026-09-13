# Iteration 54 — Filtered Resource List (`GET /api/ai/v2/resources`)

## Scope

Iteration 51 added single-resource read-back and Iteration 53 added template variables. AI clients still had no
way to discover or enumerate resources. This iteration adds an additive, paginated list surface with whitelisted
filters, without touching mutation paths, schema or policy.

## Design

- **Service.** `ResourceReadService::list(array $query)` filters non-deleted resources and returns a paginated
  summary projection (no `content`/TVs, so a listing stays bounded) plus `count`, `total`, `limit`, `offset`.
  `read()` and `list()` share the class but use different projections.
- **Filters (whitelisted):** `parent`, `template`, `context_key`, `published`, `q`/`search`
  (pagetitle/alias/description LIKE), `limit` (1..100, default 25, clamped), `offset`, `sort`
  (`id`, `pagetitle`, `alias`, `menuindex`, `editedon`, `createdon`, `publishedon`) and `dir` (`asc`/`desc`).
  Invalid values return `invalid_filter` (HTTP 400); unknown keys are ignored. LIKE input is escaped.
- **Security.** New operation `resource.list` maps to the existing `resource:read` scope (reuse, not a new
  scope); the authorized-Manager list includes it. `SecurityDecisionPipeline`, IP allowlist and policy service
  are unchanged; the read path remains site-level.
- **REST.** `GET /api/ai/v2/resources` (alongside the existing `POST`); documented in
  `docs/api/openapi.yaml`. `applicationResult()` maps `invalid_filter` to `400`.
- **MCP.** New `resource_list` tool with the same filter schema.
- **Capabilities.** `resource.list` added to `CapabilityCatalog` (`scope: resource:read`, `available`).
- **SDKs.** `listResources(array $filters)` in the PHP SDK; `listResources(params)` with
  `ResourceListItem`/`ResourceListResponse` types in the TypeScript SDK.

## xPDO findings (important)

Two xPDO behaviours affected the first implementation and are now avoided:

- `xPDO::getCollection($class, $criteria, $cacheFlag)` — the third parameter is **not** a query-options array.
  Passing `['limit' => ..., 'sortby' => ...]` only sets the cache flag, so limit/sort/offset are silently
  ignored. The list path builds an explicit `newQuery()` and calls `limit()`/`sortby()`.
- Flat criteria keys like `OR:pagetitle:LIKE` OR the **entire** preceding clause (including `deleted = 0`), so
  they match almost everything instead of acting as a grouped search. The list path uses a nested
  `where([...], xPDOQuery::SQL_OR)` group, producing `deleted = 0 AND (pagetitle LIKE ... OR alias LIKE ...)`.

The same two patterns exist in older manager code (`ResourceExplorerService::search/tree`,
`RestApi::profiles`); they are latent issues outside this iteration's additive scope and were left untouched.

## Evidence

Local run against the Docker stack (MODX 3.2.2-pl, PHP 8.2.33), version identities at `0.4.0`:

```text
composer lint                                        PASS
composer verify-static-contract                      Static contract: PASS
composer test -- --testsuite unit,contract,security  OK (62 tests, 192 assertions)
composer test -- --testsuite sdk                     OK (11 tests, 52 assertions)
bash scripts/sdk-typescript-check.sh                 TYPESCRIPT SDK: PASS (14 runtime tests)
MODX_ROOT=... vendor/bin/phpunit --testsuite integration   OK (62 tests, 3292 assertions)
bash scripts/ts-live-check.sh                        TYPESCRIPT SDK LIVE HTTP: PASS (14 tests)
bash scripts/verify-supply-chain-pins.sh             SUPPLY CHAIN PINS: PASS

# container: rebuild + install, restart for opcache
Transport Package installation: PASS (Signature: aibridge-0.4.0)
curl /api/ai/v2/health                               {"status":"ok","version":"0.4.0",...}
MODX runtime verification: PASS
PACKAGE REPRODUCIBILITY: PASS (aibridge-0.4.0, sha256 778d061a4c013f19c06e04814f5edd42fbab76f993864dd59634bffc417c864e, 143830 bytes)

# new/changed coverage
+ RestApiTest::testResourceListFiltersAndPagination  (q filter, total/count, limit/offset, summary shape, 400s)
+ RestApiTest::testResourceListRequiresScope         (site:read token -> 403 insufficient_scope)
+ AuthorizationTest                                  (resource.list needs resource:read, not site:read)
+ McpTransportTest                                   (resource_list listed; scope denial -32003)
+ ClientContractTest / OpenApiContractTest           (SDK path + documented route)
+ TS runtime                                        (listResources query string)
+ TS live                                           (REST list by alias filter; MCP resource_list)
```

## Compatibility and security

- Additive: no new scope, schema, migration, setting or mutation; a new route/operation/tool only. Existing
  tokens with `resource:read` gain list access; tokens without it receive `403 insufficient_scope`.
- The list projection excludes `content` and TVs; it exposes metadata only, and `delete`/`publish` policy is
  untouched.

## Certification

`Release` dispatch on `main`, commit `07f3c72`, run `34758755913`: `verify` PASS (incl. `SUPPLY CHAIN PINS: PASS`),
`certify` PASS (`OK (135 tests, 700 assertions)`, `TYPESCRIPT SDK LIVE HTTP: PASS`,
`STABLE certification gates passed.`), `package` PASS. The CI artifact sha256 `778d061a…` (143830 bytes) matches
the local build. Evidence artifact: `release-evidence-07f3c729766b2f06f1422e8074d52fcfe95b6eac`.

## Status

Stable `0.4.0`, certified by run `34758755913`; tag `v0.4.0` and the GitHub Release are recorded in
`docs/release/0.4.0.md`.
