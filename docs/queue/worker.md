# Worker Contract

A worker executes one job at a time through `Worker::runOnce()`.

1. Claim one `queued` job.
2. Resolve its registered handler.
3. Execute with `JobContext` for progress and heartbeat.
4. Redact the result before persistence.
5. Mark `completed`, or mark/requeue `failed` according to retry policy.
6. Write an audit event.

The worker must not execute arbitrary PHP from the job payload. Job `type` is resolved only through `JobRegistry`.

## Supervised execution

The CLI worker (`worker.php`) runs each claimed job in a child process by default
(`Worker::runOnceSupervised()`). The parent passes the claimed job id through the `AIBRIDGE_EXEC_JOB`
environment variable, polls the child, and terminates it with SIGKILL once the job's
`timeout_seconds` budget is exhausted.

- The child initializes its own MODX/database connection, so a killed process cannot leave an open
  transaction behind: the server rolls it back when the connection drops.
- A hard timeout is terminal: the job is marked `failed` with `job_timeout` (never requeued) and an
  `in_progress` idempotency key is abandoned so a pre-mutation abort stays retryable.
- Child stderr is captured (bounded, redacted) and attached to the audit diagnostic.
- If the child exits without settling the job, the job is failed as retryable `job_failed`.
- `--inline` disables supervision and executes jobs in the worker process (debugging/troubleshooting).
- The child is spawned with the worker's environment plus `AIBRIDGE_EXEC_JOB`; the PHP binary is
  `PHP_BINARY` (fallback `php`).
