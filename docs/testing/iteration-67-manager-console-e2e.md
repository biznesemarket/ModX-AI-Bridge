# Iteration 67 — Manager E2E over the Operations Console and Dependabot Guard

Tests/CI-configuration only: no `core/`/`assets/` runtime change, so the transport package is byte-identical
and no version bump, tag or GitHub Release was made. Stable stays `0.11.0`.

## Scope (HANDOFF §6 priorities, in order)

1. **New observations**: `docs/ai-agent/baseline/known-defects.md` reviewed — every entry 1–46 is closed,
   nothing to register.
2. **Read-back** (`modx://resource/{id}`): no concrete request, so nothing changed (redaction and depth were
   already closed in 0.11.0).
3. **Manager E2E over the Operations Console**: implemented as a new integration test.
4. **Dependabot**: PHP 8.5 is now refused by policy in `.github/dependabot.yml`, not only by closing PR #1.

## Design

### `tests/Integration/ManagerConsoleE2ETest.php` (5 tests)

The test boots a real initialized `modX` (anonymous subclass with a toggleable `hasPermission()`), sets
`$modx->error` explicitly, requires the installed `processors/manager/resource-operation.class.php` and
`processors/manager/workflow.class.php`, and drives the manager surface through the real processors — not the
services directly, unlike `ResourceMutationE2ETest`/`ApprovalWorkflowE2ETest`.

- **Mutation lifecycle visible in the console read models**: `ResourceOperationProcessor` (`resource.update`)
  → `ResourceOperationService` → change → queue → real `Worker`/`ResourceExecutionJobHandler` → verification.
  Asserts the resource after execution, and that `OperationsConsoleService` projects the change (`completed`,
  matching `job_id`, decoded `input`/`after` arrays, no raw `*_json` keys), the completed job, and the
  `change_created` / `change_execution_dispatched` / `resource_execution` audit events.
- **`WorkflowProcessor` list delegation**: `mode=list` returns the console projections (changes with decoded
  `input`, approvals array), i.e. the manager list path and the console read path agree.
- **Dangerous operation through the manager surface**: `resource.publish` via
  `ResourceOperationProcessor` returns `pending_approval` with `approval_id` and **no** `job_id`; executing
  the pending change through `WorkflowProcessor` (`mode=execute`) fails closed (`workflow_error`) and leaves
  the resource unpublished; the console shows the pending change/approval; after `approve_decision` →
  `approve` → `execute` the job completes, the resource is published and the console shows
  `completed`/`approved`.
- **Preview stays synchronous and queue-free**: `resource.preview` with `sync=1` returns
  `data.persisted=false` and does not mutate the resource.
- **Fail-closed validation and guards**: `profile_required`, `profile_not_found`, `operation_not_allowed`,
  plus the `manager_auth_required` / `manager_permission_required` guard for both
  `ResourceOperationProcessor` and `WorkflowProcessor` (extending the Iteration 62 guard coverage to the two
  processors that actually mutate).

### Dependabot

`.github/dependabot.yml` now ignores `php >= 8.5` under the `/docker/modx` Docker ecosystem, with the reason
inline: `php:8.2-apache` is the certified runtime for MODX 3.2.2-pl, the 8.5 bump additionally needs a
Dockerfile extension-list rewrite (`lexbor`/`dom`) and a full re-certification (HANDOFF §5). Digest updates
within 8.2.x are still proposed; the ignore rule removes the recurring PR noise instead of relying on manually
closing it each week.

## Evidence

Local run against the Docker stack (MODX 3.2.2-pl, PHP 8.2.33, MySQL 8.0):

```text
php -l tests/Integration/ManagerConsoleE2ETest.php                    No syntax errors
phpunit --testsuite integration --filter ManagerConsoleE2ETest        OK (5 tests, 61 assertions)
phpunit --testsuite integration                                       OK (113 tests, 11026 assertions)
composer validate --no-check-publish --strict                         ./composer.json is valid
composer lint                                                         PASS
composer verify-static-contract                                       Static contract: PASS
composer test -- --testsuite unit,contract,security                   OK (71 tests, 226 assertions)
composer test -- --testsuite sdk                                      OK (11 tests, 52 assertions)
```

Not run (tests/config only, no package change): `scripts/verify-package-reproducibility.php`, TS runtime
tests, `scripts/ts-live-check.sh`, the CI `Release` gate. `.github/dependabot.yml` was reviewed manually
(no local YAML linter is available on this host); the block mirrors the existing update entries and the
GitHub-side dependabot run validates it.

## Compatibility and security

- No runtime code changed: the security boundary (manager authentication + `aibridge_manage`, approval-gated
  publish, `SecurityDecisionPipeline`) is only asserted, never bypassed. The new test proves publish cannot
  execute before approval through the manager processor path.
- The console projection assertions lock the Iteration 65/66 shapes: decoded `input`/`after`, no `*_json`
  leaks, no token material.
- The Dependabot ignore rule is fail-closed with respect to certification: it prevents an unverified PHP
  runtime from being proposed for the certified stack.

## Status

PASS (local deterministic + integration). Changes are not committed yet; commit/push requires an explicit
command.
