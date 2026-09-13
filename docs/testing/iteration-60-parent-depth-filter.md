# Iteration 60 — Recursive Parent Filter and Parent Sort in Read-Back

## Scope

1. **Recursive `parent` filter**: `GET /api/ai/v2/resources` (and the `resource_list` MCP tool) accepts an
   optional `depth` (1..10, default 1) that widens the `parent` filter from direct children (the historical
   behaviour) to descendants down to `depth` tree levels. Soft-deleted resources are skipped while walking,
   so a soft-deleted node hides its subtree.
2. **`parent` in `sort`**: the `parent` column joins the whitelisted sort columns (`sort=parent`, `dir`).
3. Out of scope: any additional projection filters on the `modx://resource/{id}` template (no request yet).

## Design

- **`ResourceReadService::list()`** parses `parent` as before (int >= 0, exact `parent` criteria). When `depth`
  is present it must be numeric 1..10 and requires `parent`, otherwise `400 invalid_filter`
  (`depth requires parent.`). With `depth = 1` the query is byte-for-byte the old exact-parent query; with
  `depth > 1` the `parent` criterion is replaced by `id:IN` over the collected descendant ids.
- **Recursion** (`ResourceReadService::descendantIds()`): level-by-level `parent:IN` queries (the MODX
  `getChildIds()` helper was rejected because it reads the in-memory `resourceMap`, which the REST path does
  not guarantee to be loaded, and it does not skip deleted rows). Each level selects only the PK
  (`select('id')`) so a large subtree is not hydrated as full rows; `deleted = 0` is applied per level.
  The depth cap (10, matching the MODX convention) bounds the work.
- **Empty subtree** returns an empty page without running the list queries (`id:IN []` is never emitted).
- **Sorting**: `SORTABLE` now includes `parent`; the sort is still qualified as `modResource.parent`, which
  keeps the TV join (`aibridge_tv`) unambiguous.
- **Security**: same route, `resource.list` operation, `resource:read` scope and `SecurityDecisionPipeline`
  decision; no scope, policy, schema, migration or setting change. Read-only, profile isolation unchanged.

## Evidence

Local run against the Docker stack (MODX 3.2.2-pl, PHP 8.2.33), version identities at `0.8.0` during
verification (bumped to `0.9.0` in the release commit):

```text
composer validate --no-check-publish --strict        valid
composer lint                                        PASS
composer verify-static-contract                      Static contract: PASS
composer test -- --testsuite unit,contract,security  OK (63 tests, 201 assertions)
composer test -- --testsuite integration             OK (76 tests, 5575 assertions)
Transport Package installation: PASS (Signature: aibridge-0.8.0)

# new coverage
+ RestApiTest::testResourceListFiltersByParentDepthAndSortsByParent
  - parent without depth stays direct-children only (2)
  - depth=2 includes children + grandchildren only, excludes great-grandchild
  - depth=10 returns the full subtree (4), sibling tree stays isolated (1)
  - leaf parent + depth=10 returns an empty page
  - depth without parent, depth=0, depth=11, depth=abc -> 400 invalid_filter
  - sort=parent&dir=asc orders by the parent column
  - a soft-deleted node is skipped and its subtree is not walked (4 -> 2)
```

## Compatibility and security

- Additive read-only change: one new optional query parameter on the existing `resource.list` operation plus
  `parent` added to the sort whitelist. Requests without `depth` behave exactly as before.
- No new scope, route, schema, migration or setting; mutation paths and `SecurityDecisionPipeline` are not
  touched.

## Certification

`Release` dispatch on `main`, commit `5d83170`, run `34771312099`: `verify` PASS (incl. `SUPPLY CHAIN PINS: PASS`),
`certify` PASS (`OK (150 tests, 860 assertions)`, `TYPESCRIPT SDK LIVE HTTP: PASS`,
`STABLE certification gates passed.`), `package` PASS. The CI artifact sha256
`59d4f0ab7d0cce445c7442b51c629538e6b21a9686e8582db2ba01472a6e54fd` (146359 bytes) matches the local build.
Evidence artifact: `release-evidence-5d831706f38b82e58dc42f573e4110d12ec43848`.

## Status

Stable `0.9.0`, certified by run `34771312099`; tag `v0.9.0` and the GitHub Release are recorded in
`docs/release/0.9.0.md`.
