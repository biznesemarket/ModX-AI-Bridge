# Iteration 51 — Resource Read-Back API and `aibridge_version`

## Scope

AI clients could mutate resources but not read back the persisted state: after a queued `create`/`update` the
only feedback was the job's `result_json`. Add an additive read-back surface, and expose the component version
as a real MODX system setting (previously only a code fallback existed).

## Design

- **New operation and scope.** `Authorization` gains `resource:read` (constant `SCOPE_READ_RESOURCE`) mapped to
  the `resource.read` operation; authorized Manager-channel requests may read as well. Tokens without the new
  scope receive `403 insufficient_scope`, so existing tokens keep working unchanged.
- **`Services/ResourceReadService`.** Reads one non-deleted `modResource` and returns a stable whitelisted
  projection (strings, ints and booleans normalized): `id`, `pagetitle`, `longtitle`, `description`,
  `introtext`, `content`, `alias`, `class_key`, `context_key`, `publishedon`, `createdon`, `editedon`,
  `parent`, `template`, `menuindex`, `published`, `hidemenu`, `deleted`, `searchable`. Missing/soft-deleted
  resources return `resource_not_found`; template variables are intentionally not included yet.
- **REST.** `GET /api/ai/v2/resources/{id}` goes through `SecurityDecisionPipeline` (operation `resource.read`)
  and `Application::resourceRead()`; `applicationResult()` maps `resource_not_found` to `404`. The route is
  documented in `docs/api/openapi.yaml`.
- **MCP.** New `resource_read` tool (required argument `id`) maps to the same Application service and
  operation; documented in `docs/mcp/tools.md`.
- **SDKs.** `getResource(int $id)` / `getResource(id: number)` in the PHP and TypeScript clients; the live HTTP
  E2E now verifies the updated state through the API instead of parsing job results.
- **`aibridge_version` setting.** Added to `_build/elements/settings.php` (area `operations`), so packages carry
  it up to now 20 settings. `/health` and `/ready` report the setting value with the existing code fallback
  (`$modx->getOption('aibridge_version', null, '0.1.3')`); the Operations Console and `scripts/readiness.php`
  already consumed the option.

## Evidence

Local run against the Docker stack (MODX 3.2.2-pl, PHP 8.2.33):

```text
composer test -- --testsuite unit,contract,security  OK (60 tests, 190 assertions)
composer test -- --testsuite sdk                     OK (11 tests, 51 assertions)
./scripts/sdk-typescript-check.sh                    TYPESCRIPT SDK: PASS (13 runtime tests)
MODX_ROOT=... composer test -- --testsuite integration  OK (58 tests, 2099 assertions)

# container: rebuild + install
Transport Package installation: PASS (Signature: aibridge-0.1.3)
SELECT COUNT(*) ... aibridge_%                       20
curl /api/ai/v2/ready                                {"status":"ready","version":"0.1.3",...}
curl /api/ai/v2/health                               {"status":"ok","version":"0.1.3",...}

bash scripts/ts-live-check.sh                        TYPESCRIPT SDK LIVE HTTP: PASS (13 tests)
  + resource read-back exposes the updated state
  + resource read-back returns 404 for a missing resource
  + MCP resource_read returns the updated projection
```

Operational note: installing the package re-imports setting defaults (test-runtime values such as
`aibridge_require_https` are reset); `scripts/ts-live-check.sh` and `scripts/configure-test-runtime.php`
re-apply them before tests. After installing a new build into a running stack, restart `modx` (opcache).

## Compatibility and security

- Additive API/contract change (new route, new scope, new MCP tool, new setting); no schema or migration
  change, no existing behaviour altered. Tokens must be granted `resource:read` explicitly.
- The read path uses the same `SecurityDecisionPipeline` as other operations; the projection contains no
  tokens, audit data or queue internals.
- MCP tool failures return `-32003` when the scope is missing; `resource_not_found` is a normal tool-result
  payload, not a JSON-RPC error.

## Status

Unreleased (targets `0.2.0`). Next: release iteration with version bump (all identities, including README),
full gate, tag and GitHub Release.
