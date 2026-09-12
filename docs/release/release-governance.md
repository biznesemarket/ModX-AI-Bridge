# Release Governance

Iteration 20 defines the final release gate for `Stable`.

A release is eligible for `Stable` only when every mandatory gate passes. The pipeline is fail-closed: an unavailable runtime, missing security result, failed contract, or failed verification is a release failure, not a warning.

Mandatory gates:

1. PHP syntax and static contract checks.
2. Unit tests.
3. API and MCP contract tests.
4. Security regression tests.
5. Real MODX integration against the supported compatibility matrix.
6. Queue/concurrency tests.
7. Multi-site isolation tests.
8. Idempotency tests.
9. Transaction/rollback tests.
10. Post-execution verification tests.
11. Transport Package build and integrity verification.
12. Migration upgrade/rollback checks.
13. Production security checklist.

`Stable` is a certification state, not a package version. A release can be packaged while remaining `Review` until all mandatory gates have evidence.
