# Iteration 64 — Publish-approval binding and MCP channel propagation (`0.9.3`)

Date: 2026-09-13
Base: Stable `0.9.2` (`main` = `17fcd1a`). Release: `0.9.3` (patch).

Follow-up to the local code review of Iteration 63. No new scope, route, schema, migration or setting.

## Findings and fixes

1. **Publish approval was not bound to the caller (security).** `SecurityDecisionPipeline::isApprovedForChange()`
   only checked that an `Approval` row was `approved` for the given `change_id`; it did not verify the
   requesting principal's profile, the change's operation, or that the change targets the same resource, and
   the approval was not consumed. A token holding `resource:publish` could publish an arbitrary resource using
   any approved approval row (including another profile's) and repeat it. The Iteration 63 MCP
   `approval_id`/`change_id` forwarding made this reachable via MCP.
   Fix: `isApprovedForChange($approvalId,$changeId,$principal,$request)` now loads the `ChangeRequest`, requires
   `operation === 'resource.publish'`, requires `profile_id === principal.profile_id` when the principal is
   profile-scoped, and requires `change.resource_id === request.resource_id` when both are set. `RestApi`,
   `ResourceExecutionService` and `McpServer` now pass the target `resource_id` into the pipeline.
2. **Manager-connector MCP channel was dropped (correctness).** The transport check in `McpServer::callTool()`
   used the trusted `channel`, but each tool handler rebuilt a partial request without it, so the
   execution-layer pipeline saw no manager channel and rejected manager MCP mutations with `ip_not_allowed`.
   Fix: `callTool()` sets `$arguments['_channel']` and every mutation/preview handler forwards
   `channel` into the `Application` request. Fails closed before and after; no privilege change.
3. **`after_json` verification mismatch for `published` (correctness).** `ChangeRequestService::create()`
   derived `after_json` from the raw input, so an update carrying `published` recorded an expected publish
   state that `ResourceExecutionService` intentionally no longer applies, and post-execution verification
   failed the change after the other fields were committed. Fix: `published` is dropped for non-publish
   operations in `ChangeRequestService::create()` (matching the writable whitelist).
4. **MCP tool contract (correctness).** `resource_publish` required `approval_id` but not `change_id`, while
   the pipeline requires both. Fix: `change_id` added to `required`.
5. **Docs (accuracy).** Corrected the SDK identity note (`clientInfo` bumps; the SDK `User-Agent` stays `0.9`
   on patches) and the `install-package.php` examples (full path/`.transport.zip`).
6. **Test hygiene.** `SiteDiscoveryDeterminismTest` and `McpPublishApprovalTest` now remove the rows they
   create in `tearDownAfterClass()` instead of accumulating fixtures on the discovery hot path.

## Tests

- `McpPublishApprovalTest::testPublishToolRejectsApprovalForAnotherResource` — approval for resource A cannot
  publish resource B.
- `McpPublishApprovalTest::testPublishToolRejectsCrossProfileApproval` — a profile-B token cannot publish with
  a profile-A approval.
- Existing `testPublishToolExecutesWithApprovedChange` / `testPublishToolWithoutApprovalIsDenied` still pass.

## Local evidence (Docker stack: MODX 3.2.2-pl, PHP 8.2.33)

```text
composer lint                         -> no syntax errors
unit,contract,security                -> OK (64 tests, 206 assertions)
integration (MODX_ROOT=/var/www/html) -> OK (95 tests, 7807 assertions)
```

## Security implications

- Strictly hardening: cross-profile and replayed publish approvals are rejected; the manager MCP channel is
  now honored in the execution pipeline (previously denied), with the identity/IP still coming from the
  authenticated manager session and real transport peer.

## Migration / backward compatibility

- No schema, route, scope or setting change. A publish request must now reference an approval whose change is
  a `resource.publish` change for the same profile and resource — the intended contract.

## Remaining risks

- Backlog items 36–43 in `known-defects.md` remain open (info disclosure, index/profile coupling, dead code,
  worker timeout semantics, cache scope, console projections).
- Live HTTP E2E and TypeScript live checks are executed by the CI `Release` gate.
