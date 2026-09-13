# Iteration 56 — Template-Variable Filter for the Resource List

## Scope

The filtered resource list (Iteration 54) could not select resources by template variable. This iteration adds
`tv_name` / `tv_value` to `GET /api/ai/v2/resources` and the `resource_list` MCP tool, additively, without
touching REST contracts beyond the new query parameters.

## Design

- **Filters.** `tv_name` restricts the list to resources that have an explicit `modTemplateVarResource` value
  for that TV; `tv_value` (optional) adds a `LIKE` match on the value. `tv_value` without `tv_name` returns
  `400 invalid_filter` (`tv_value requires tv_name.`); an unknown `tv_name` also returns `400 invalid_filter`.
  Values are trimmed, length-capped at 191 and LIKE-escaped. A known TV with no matching rows returns an empty
  success result (`count: 0`, `total: 0`).
- **Lookup.** The TV is resolved by name through the service container, then its matching `contentid`s are
  collected and applied as `id:IN` on the same `newQuery()` used by the existing list filters, so the summary
  projection, pagination and sorting are unchanged.
- **Semantics.** Only explicit per-resource TV overrides are matched; resources that rely on a TV `default_text`
  are not included. This mirrors the existing manager search behaviour and is documented.
- **MCP.** `resource_list` schema (and therefore the tool contract) now advertises `tv_name`/`tv_value`.
- **Security.** No new operation or scope: the filter runs inside `resource.list` / `resource:read`; read-only
  and site-level like the rest of read-back.

## xPDO finding (important)

Selecting a single non-PK column on a collection (`$query->select('contentid')` then `getCollection(...)`) makes
the hydrated objects lack their primary key. The first field access then triggers `xPDOObject::_loadFieldData()`
with a null primary key, which issues an unfiltered query and exhausted memory (128 MB) on the live stack. The
implementation instead fetches full `modTemplateVarResource` objects (PK intact) and reads `contentid`. Avoid
partial selects on collection queries in this codebase.

## Evidence

Local run against the Docker stack (MODX 3.2.2-pl, PHP 8.2.33), version identities at `0.6.0`:

```text
composer validate --no-check-publish --strict        valid
composer lint                                        PASS
composer verify-static-contract                      Static contract: PASS
composer test -- --testsuite unit,contract,security  OK (63 tests, 201 assertions)
composer test -- --testsuite sdk                     OK (11 tests, 52 assertions)
bash scripts/sdk-typescript-check.sh                 TYPESCRIPT SDK: PASS (14 runtime tests)
MODX_ROOT=... vendor/bin/phpunit --testsuite integration   OK (65 tests, 4023 assertions)
bash scripts/ts-live-check.sh                        TYPESCRIPT SDK LIVE HTTP: PASS (15 tests)
bash scripts/verify-supply-chain-pins.sh             SUPPLY CHAIN PINS: PASS

# container: rebuild + install, restart for opcache
Transport Package installation: PASS (Signature: aibridge-0.6.0)
curl /api/ai/v2/health                               {"status":"ok","version":"0.6.0",...}
MODX runtime verification: PASS
PACKAGE REPRODUCIBILITY: PASS (aibridge-0.6.0, sha256 19c1398bd4461b24df660ea87db41df365dec07a7b133ce0c4bad18bce53808d, 145059 bytes)

# new/changed coverage
+ RestApiTest::testResourceListFiltersByTemplateVariable  (tv_name, tv_value, no-match, orphan tv_value, unknown TV)
+ TS live: resource list filters by template variable     (explicit TV value set at create)
+ TS live: MCP resource_list with tv_name/tv_value
```

## Compatibility and security

- Additive: two new query parameters on an existing read operation and tool; no new scope, schema, migration,
  setting or mutation. Requests without `tv_name` behave exactly as before.
- Only metadata is returned by the list; `content`/TVs stay out of the summary projection, and the filter is
  authorized by the same `resource:read` check.

## Status

Implementation complete and locally verified; release target `0.6.0` (additive minor). Next, per the
established flow: bump all identity files (including README and the SDK `User-Agent`) -> full CI gate ->
evidence/tag -> tag-run -> GitHub Release.
