# Worker Contract

A worker executes one job at a time through `Worker::runOnce()`.

1. Claim one `queued` job.
2. Resolve its registered handler.
3. Execute with `JobContext` for progress and heartbeat.
4. Redact the result before persistence.
5. Mark `completed`, or mark/requeue `failed` according to retry policy.
6. Write an audit event.

The worker must not execute arbitrary PHP from the job payload. Job `type` is resolved only through `JobRegistry`.
