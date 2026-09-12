# Jobs, Queue & Asynchronous Execution

Iteration 12 introduces persistent asynchronous execution for long-running AI/MODX operations.

## State machine

```text
queued → running → completed
                 ↘ failed
                 ↘ queued (retry)
                 ↘ cancelled
queued → cancelled
```

`completed` and `cancelled` are terminal states. A failed job is requeued when `attempts < max_attempts` and the error is retryable.

## Lease and worker safety

A worker claims a job with a database row lock. `locked_at` and `locked_by` form a lease. `requeueStale()` returns abandoned jobs to `queued` after the configured lease period.

The worker records progress and heartbeats. Timeout is measured at the worker boundary. This is a cooperative/observed timeout; a hard process-level kill must be supplied by the process supervisor (systemd, Supervisor, Kubernetes, etc.).

## Idempotency

Mutation jobs can carry the same `idempotency_key` used by the synchronous execution engine. The job payload must preserve the principal and request identity. The queue does not weaken the existing security pipeline: `ResourceExecutionJobHandler` invokes `ResourceExecutionService`, which performs authentication-derived authorization, policy, idempotency, QA, snapshot, transaction and audit controls.

## Retry

Retry uses bounded exponential backoff. Non-retryable failures are represented by `NonRetryableJobException`.

## Large operations

HTTP endpoints should enqueue work and return a job identifier rather than keeping the HTTP connection open. Clients poll job status or use an external worker/orchestrator.
