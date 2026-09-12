# Workflow Security

1. Approval is not permission. The caller must still pass the Security Decision Pipeline.
2. Approval is scoped to one change request.
3. Execution accepts only `approved` changes.
4. Browser code cannot execute MODX CRUD directly.
5. The existing ResourceExecutionService remains the mutation authority.
6. Audit records are emitted for creation, submission, approval, rejection and dispatch.
7. Secrets are redacted before durable change payload storage.
