# Iteration 31 — Approval Workflow E2E Evidence

Environment: Docker Desktop on Windows, `docker compose` stack from `docker-compose.yml` — MODX 3.2.2-pl,
PHP 8.2.33, MySQL 8.0. Commands executed inside the `modx` container (workdir `/workspace/modx-ai-bridge`).

## Scope

Human-in-the-loop approval workflow over the real database and queue:
`draft → submit → approve/reject → execute → verify → audit`, approval/change binding, terminal-state
guards, execute-only-approved, and fail-closed publish without a valid approval.

## Evidence

- `MODX_ROOT=/var/www/html vendor/bin/phpunit --filter ApprovalWorkflowE2ETest` → **OK (6 tests, 41 assertions)**:
  1. `draft → submit → reject` transitions recorded, rejection reason stored, `rejected` is terminal, a
     rejected change cannot execute or be rejected twice; audit `change_created` / `change_submitted` /
     `change_rejected` present.
  2. An approved approval binds to exactly one change: it cannot authorize a second change
     (`Approval does not authorize this change.`); the bound change approves with `approval_id` persisted.
  3. Execution accepts only `approved` changes: `draft` and `pending_approval` dispatch attempts are rejected.
  4. Happy path: explicit approval → `approved` → dispatch → queue → verification `completed`; resource
     updated; audit `change_created` / `change_submitted` / `change_approved` / `change_execution_dispatched`;
     completed change is terminal (no second approve/execute).
  5. Approval decision lifecycle: invalid decision rejected; `rejected` decision is final and cannot
     authorize the change (change stays `pending_approval`).
  6. Manager publish without an approval reference is denied by the security pipeline
     (`approval_required`) and the resource stays unpublished.
- `MODX_ROOT=/var/www/html vendor/bin/phpunit --testsuite unit,contract,security` → **OK (57 tests, 181 assertions)**,
  including the full `ChangeState` transition matrix and terminal-state invariants.
- `MODX_ROOT=/var/www/html vendor/bin/phpunit` → **OK (93 tests, 328 assertions)**.

## Notes / remaining

- The approval record and the change state are intentionally separate: `decide(approved)` does not by
  itself move the change to `approved`; `ChangeRequestService::approve()` validates the binding and performs
  the transition. Rejecting a change is explicit (`ChangeRequestService::reject()`), matching the documented
  state machine.
- The `WorkflowProcessor` Manager modes (`request_approval`, `approve_decision`, `reject_decision`,
  `execute`) delegate to these services; processor-level authentication is covered by manager security tests.
- Multi-site isolation of approvals/tokens/audit/snapshots remains for Iteration 32.
