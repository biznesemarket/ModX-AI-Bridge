# Approval Workflow

The workflow separates intent from execution. Mutation requests become durable Change Requests with a before/after snapshot, deterministic field diff and Content QA result.

## Risk policy

Create/update are auto-approved by the default Manager workflow. Delete and publish enter `pending_approval` and require a human approval record bound to the exact `change_id`. This is a default, not a universal policy; deployments may tighten it.

## Verification

An approval is valid only when `approval.change_id == change.change_id` and `approval.status == approved`. The Security Decision Pipeline verifies this binding before publish execution.

## Execution

Approved changes are dispatched to the existing queue. The worker calls ResourceExecutionService and records `completed` or `failed` on the Change Request.
