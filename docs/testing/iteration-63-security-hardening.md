# Iteration 63 — Security hardening and discovery determinism (`0.9.2`)

Date: 2026-09-13
Base: Stable `0.9.1` (`main` = `6c56472`). Release: `0.9.2` (patch).

## Scope

Autonomous audit findings fixed and certified in this iteration. No new scope, route, schema, migration or
setting; the HTTP/MCP contract is unchanged except that `published` is no longer writable through
`resource.update`/`resource.create` (it was never part of the documented update contract).

## Fixes

1. **Publish-approval bypass (P1, security).** `ResourceExecutionService::sanitizePayload()` accepted
   `published`, so a principal with `resource:write` could set `published = 1` through
   `resource.update`/`resource.create` and bypass the approval-gated `resource.publish` operation.
   `published` was removed from the writable whitelist; publish state is set only by the publish branch.
   Regression: `ResourceMutationE2ETest::testUpdateCannotBypassPublishApproval`.
2. **Non-deterministic site discovery (P2, correctness).** `TemplateInspector`, `TVInspector`,
   `ChunkInspector`, `SnippetInspector` passed `sortby` as `getCollection()`'s cache flag (silently ignored),
   so the contract section order — and the site fingerprint — depended on storage order. They now build an
   `xPDOQuery` with an explicit `sortby(name|templatename, ASC)`.
   Regression: `SiteDiscoveryDeterminismTest`.
3. **`WorkflowProcessor::listChanges()/listApprovals()` (P2).** Options-as-cacheFlag (same class as
   Iterations 58/61): `limit`/`sortby` ignored, unbounded/arbitrary lists. Now explicit `newQuery()` with
   `limit(200)` and `created_at`/`requested_at DESC`.
4. **`IpAllowlist` malformed CIDR (P2, security).** `/33` on IPv4, `/129` on IPv6, negative or non-numeric
   prefixes matched every address. Prefix length is now validated (fail closed).
   Regression: `IpAllowlistTest::testRejectsMalformedPrefixLengths`.
5. **Manager-connector MCP surface (P1, security).** `processors/mcp.class.php` had no permission guard and
   derived `scopes`/`_client_ip` from the request. It now extends `AdminProcessor` (`aibridge_manage`),
   derives the principal from the authenticated MODX user and the real transport peer, and never trusts
   request-supplied identity.
6. **MCP publish approval forwarding (P2, functional).** `McpServer::callTool()` never forwarded
   `approval_id`/`change_id` into the security pipeline, so `resource_publish` was always denied when
   approvals are required. Both fields are now forwarded; the client IP is taken from the trusted transport
   principal instead of `params`.
   Regression: `McpPublishApprovalTest` (approved → publishes; no approval → `-32003`, resource unchanged).

## Files changed

- `core/components/aibridge/src/Execution/ResourceExecutionService.php`
- `core/components/aibridge/src/Security/IpAllowlist.php`
- `core/components/aibridge/src/Inspectors/{Template,TV,Chunk,Snippet}Inspector.php`
- `core/components/aibridge/src/MCP/McpServer.php`
- `core/components/aibridge/src/Api/RestApi.php`
- `core/components/aibridge/processors/manager/workflow.class.php`
- `core/components/aibridge/processors/mcp.class.php`
- `tests/Unit/Security/IpAllowlistTest.php`, `tests/Integration/ResourceMutationE2ETest.php`
- `tests/Integration/SiteDiscoveryDeterminismTest.php`, `tests/Integration/McpPublishApprovalTest.php`
- version identity (config, settings, health, MCP serverInfo, fallbacks, README, CHANGELOG, SDK, package-lock)
- `docs/ai-agent/baseline/known-defects.md` (items 8/9 re-closed; new backlog 36–43)

## Local evidence (Docker stack: MODX 3.2.2-pl, PHP 8.2.33)

```text
composer lint                         -> no syntax errors
unit,contract,security                -> OK (64 tests, 206 assertions)
sdk                                   -> OK (11 tests, 52 assertions)
integration (MODX_ROOT=/var/www/html) -> OK (93 tests, 7503 assertions)
verify-static-contract                -> Static contract: PASS
Transport Package installation        -> PASS (Signature: aibridge-0.9.2)
PACKAGE REPRODUCIBILITY               -> PASS (aibridge-0.9.2, sha256 721e9ce0b794e0fdeaf4441b1f2b146c48a93b508665466b31d2a2d70a95d34a)
```

## Security implications

- Strictly hardening: the publish gate can no longer be bypassed through update/create; the manager-connector
  MCP path is permission-gated and no longer trusts request identity; malformed CIDR rules fail closed.
- `delete` remains disabled by default; `publish` remains approval-gated; tokens are not logged;
  `SecurityDecisionPipeline` remains on every mutation path.

## Migration / backward compatibility

- No schema, route, scope or setting change. Additive/behavioral only:
  `published` is no longer accepted as an update/create field (undocumented); the REST/MCP shapes are
  otherwise unchanged.
- SDK `User-Agent`/clientInfo version is bumped to `0.9.2` (patch identity), consistent with previous patch
  releases.

## Remaining risks

- Live HTTP E2E and TypeScript live checks are executed by the CI `Release` gate, not locally.
- Backlog items 36–43 in `known-defects.md` are known and tracked (info disclosure, index/profile coupling,
  dead code, worker timeout semantics, cache scope, console projections).
