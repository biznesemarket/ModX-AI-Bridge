# Iteration 53 — Template Variables in the Read-Back Projection

## Scope

Iteration 51 added `GET /api/ai/v2/resources/{id}` with a whitelisted projection but deliberately excluded
template variables. AI clients that set TVs through `resource_create`/`resource_update` could not read them
back. This iteration closes that gap additively, without a new operation, scope, route or migration.

## Design

- **`Services/ResourceReadService`.** The read projection now adds a `tvs` map for every TV bound to the
  resource template (`$resource->getMany('TemplateVars')`), keyed by TV name — the same key convention as the
  `tv:<name>` write contract (`ResourceExecutionService`, `ChangeRequestService`, `ContentContractService`).
- **Value normalization.** Scalar values are normalized to strings (or `null`); structured values (for example
  MIGX/JSON TV types) are JSON-encoded, so `tvs` is always a flat `name -> string|null` map and never leaks a
  raw PHP array into the envelope.
- **Security boundary unchanged.** TVs are returned through the existing `resource.read` operation, so the
  `resource:read` scope, IP allowlist, policy service and `SecurityDecisionPipeline` all still apply. No new
  scope and no relaxation of `delete`/`publish` policy. Read-back remains site-level (as in Iteration 51);
  profile isolation is not part of the read path.
- **Capability catalog.** `resource.read` (scope `resource:read`, status `available`) is now listed by
  `CapabilityCatalog`, closing the Iteration 51 gap where the read surface was active but not advertised.
- **Contract surfaces.** OpenAPI summary and `docs/mcp/tools.md` now document the `tvs` map. The TypeScript SDK
  gains `ResourceProjection`/`ResourceTvValues`/`ResourceReadResponse` types (`getResource()` is typed); the
  PHP SDK is an untyped pass-through and needed no change. No new route, MCP tool or setting.

## Evidence

Local run against the Docker stack (MODX 3.2.2-pl, PHP 8.2.33), version identities at `0.3.0`:

```text
composer validate --no-check-publish --strict          valid
composer lint                                          PASS (all PHP files, incl. ResourceReadService.php)
composer verify-static-contract                        Static contract: PASS
composer test -- --testsuite unit,contract,security    OK (60 tests, 190 assertions)
composer test -- --testsuite sdk                       OK (11 tests, 51 assertions)
bash scripts/sdk-typescript-check.sh                   TYPESCRIPT SDK: PASS (13 runtime tests, type build clean)
MODX_ROOT=... vendor/bin/phpunit --testsuite integration   OK (59 tests, 2748 assertions)
bash scripts/ts-live-check.sh                          TYPESCRIPT SDK LIVE HTTP: PASS (13 tests)
bash scripts/verify-supply-chain-pins.sh               SUPPLY CHAIN PINS: PASS

# container: rebuild + install, restart for opcache
Transport Package installation: PASS (Signature: aibridge-0.3.0)
curl /api/ai/v2/health                                 {"status":"ok","version":"0.3.0",...}
PACKAGE REPRODUCIBILITY: PASS (aibridge-0.3.0, sha256 9c3e547daace05e226480e3d31fe13e4d11a27ecd154e4eb8ac5b9125d132106, 142077 bytes)

# new/changed coverage
+ RestApiTest::testResourceReadBackIncludesTemplateVariables   (tvs map carries the stored value)
+ RestApiTest::testResourceReadBackAfterMutation                (tvs is always an array)
+ client.test.mjs: getResource ... exposes template variables
+ live.test.mjs: resource read-back exposes ... template variables
+ live.test.mjs: MCP resource_read returns the updated projection (incl. tvs)
```

## Compatibility and security

- Additive only: no schema, migration, route, scope or setting change. Existing clients keep working; the new
  `tvs` key is extra data. A resource with no bound TVs returns `tvs: {}`.
- TVs are resource content, read under the already-authorized `resource.read` operation; the projection adds no
  tokens, queue internals or audit data, and no existing behavior is altered.
- `GET /resources/{id}` and the `resource_read` MCP tool (same operation) both return the `tvs` map.

## Status

Implementation complete and locally verified; version identities bumped to `0.3.0` (minor: additive read-back
feature plus capability listing). Target release: `0.3.0`. Next, per the established flow: full CI gate
(`gh workflow run release.yml --ref main`) -> evidence/tag -> tag-run -> GitHub Release.
