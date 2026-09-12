# Approval Workflow & Change Management

Iteration 19 introduces a durable change-management boundary between AI intent and MODX mutation.

## Lifecycle

`draft → pending_approval → approved → executing → completed`

Alternative terminal paths are `rejected`, `cancelled`, and `failed`.

## Human-in-the-loop

A change request can be submitted for approval. Approval is bound to the exact `change_id`; an approval record cannot authorize another change. Publishing requires an approved change when the global policy requires approval.

## Execution

Approved changes are dispatched to the existing queue. The worker invokes `ResourceExecutionService`, so the existing authentication, authorization, policy, idempotency, Content QA, snapshot, transaction, cache invalidation and audit controls remain authoritative.

## Data safety

Input and QA payloads are redacted before persistence. The approval UI must never expose bearer tokens or token hashes.
