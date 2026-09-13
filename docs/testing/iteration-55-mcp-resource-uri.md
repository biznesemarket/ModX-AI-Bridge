# Iteration 55 — MCP Resource URI `modx://resource/{id}`

## Scope

The MCP server exposed read-back only through the `resource_read` tool. MCP clients that prefer the resources
primitive had no addressable resource for a persisted MODX resource. This iteration adds the URI template
`modx://resource/{id}` and the MCP `resources/templates/list` method, without changing REST, policy or schema.

## Design

- **URI templates in the registry.** `McpRegistry::resourceTemplate()` registers `uriTemplate` entries and
  `resolveResource($uri)` matches a concrete URI against them, extracting named parameters (for example `id`
  from `modx://resource/42`). Literal template segments are `preg_quote`d; a parameter matches a single path
  segment (`[^/]+`). Exact resources still win over templates.
- **`resources/templates/list`.** New JSON-RPC method returning `resourceTemplates` (uriTemplate, name,
  description, mimeType). Templates are listed separately from `resources/list`, which keeps only concrete
  resources as before; `McpServer::capabilities()` also exposes the templates.
- **`resources/read` resolution.** The dispatcher now resolves exact resources or templates, applies the same
  `SecurityDecisionPipeline` operation as the tool (`resource.read`), and invokes the handler with the
  extracted parameters. A handler may return `{error:{code,message,data}}` to produce a JSON-RPC error; the
  `modx://resource/{id}` handler returns `-32002 Resource not found` with `reason: resource_not_found` for a
  missing or soft-deleted resource, and otherwise returns the read-back projection as
  `contents[0].text` (`application/json`).
- **SDKs.** `resourceTemplates()` added to the PHP and TypeScript MCP clients.
- **Security.** No new operation or scope: the template maps to the existing `resource.read` /
  `resource:read`; denied reads return `-32003`. Read-back remains site-level and read-only.

## Evidence

Local run against the Docker stack (MODX 3.2.2-pl, PHP 8.2.33), version identities at `0.5.0`:

```text
composer validate --no-check-publish --strict        valid
composer lint                                        PASS
composer verify-static-contract                      Static contract: PASS
composer test -- --testsuite unit,contract,security  OK (63 tests, 201 assertions)
composer test -- --testsuite sdk                     OK (11 tests, 52 assertions)
bash scripts/sdk-typescript-check.sh                 TYPESCRIPT SDK: PASS (14 runtime tests)
MODX_ROOT=... vendor/bin/phpunit --testsuite integration   OK (64 tests, 3636 assertions)
bash scripts/ts-live-check.sh                        TYPESCRIPT SDK LIVE HTTP: PASS (14 tests)
bash scripts/verify-supply-chain-pins.sh             SUPPLY CHAIN PINS: PASS

# container: rebuild + install, restart for opcache
Transport Package installation: PASS (Signature: aibridge-0.5.0)
curl /api/ai/v2/health                               {"status":"ok","version":"0.5.0",...}
MODX runtime verification: PASS
PACKAGE REPRODUCIBILITY: PASS (aibridge-0.5.0, sha256 48c8b23344fd1d6dcae2c67a529c13d44cb7d1b6b7a837c3e459621e4ff2506d, 144677 bytes)

# new/changed coverage
+ McpServerTest::testRegistryResolvesUriTemplates    (exact vs template, params, no match)
+ McpTransportTest::testResourceTemplatesListContainsResourceTemplate
+ McpTransportTest::testResourceTemplateReadRequiresScope  (site:read token -> -32003)
+ McpContractTest                                    (docs contain resources/templates/list and modx://resource/{id})
+ TS live                                           (templates/list, template read incl. tvs, missing -> -32002)
```

## Compatibility and security

- Additive: a new MCP method and a new URI template; no REST route, scope, schema, migration or setting change,
  and no mutation path touched. Existing resource entries (`modx://site/schema`, `modx://site/fingerprint`)
  behave exactly as before (extra handler arguments are ignored).
- The template resolves to the same `resource.read` operation, so IP allowlist, scope checks and policy are
  unchanged; a missing resource is a JSON-RPC `-32002` error rather than a content payload.

## Certification

`Release` dispatch on `main`, commit `1796c5d`, run `34761433276`: `verify` PASS (incl. `SUPPLY CHAIN PINS: PASS`),
`certify` PASS (`OK (138 tests, 713 assertions)`, `TYPESCRIPT SDK LIVE HTTP: PASS`,
`STABLE certification gates passed.`), `package` PASS. The CI artifact sha256 `48c8b233…` (144677 bytes) matches
the local build. Evidence artifact: `release-evidence-1796c5d7b01e0aba6b7e66b008757346506ccead`.

## Status

Stable `0.5.0`, certified by run `34761433276`; tag `v0.5.0` and the GitHub Release are recorded in
`docs/release/0.5.0.md`.
