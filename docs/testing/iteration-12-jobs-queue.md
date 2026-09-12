# Iteration 12 Test Scope

Status: Review

Required verification before Stable:

- PHP syntax check;
- xPDO schema regeneration against MODX 3.2.x;
- migration/install verification for new Job fields and indexes;
- concurrent worker claim test proving one job is claimed once;
- retry/backoff test;
- stale lease recovery test;
- cancellation race test;
- timeout behavior test;
- result/error secret redaction test;
- idempotent resource mutation through a queued job;
- end-to-end MODX integration test with MySQL.

A runtime claim must not be marked Stable until these tests run in the reproducible MODX environment.
