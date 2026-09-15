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

The worker records progress and heartbeats. Timeout is enforced twice: handlers and the execution
service consult `JobDeadline` cooperatively, and the CLI worker additionally runs each claimed job in a
supervised child process with a hard wall-clock budget. The child owns its database connection, so
terminating it rolls back an open transaction; the job is then marked as a non-retryable `job_timeout`
and the in-progress idempotency key is released. `--inline` restores single-process execution.

## Idempotency

Mutation jobs can carry the same `idempotency_key` used by the synchronous execution engine. The job payload must preserve the principal and request identity. The queue does not weaken the existing security pipeline: `ResourceExecutionJobHandler` invokes `ResourceExecutionService`, which performs authentication-derived authorization, policy, idempotency, QA, snapshot, transaction and audit controls.

## Retry

Retry uses bounded exponential backoff. Non-retryable failures are represented by `NonRetryableJobException`.

## Large operations

HTTP endpoints should enqueue work and return a job identifier rather than keeping the HTTP connection open. Clients poll job status or use an external worker/orchestrator.
