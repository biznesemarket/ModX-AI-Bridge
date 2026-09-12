# Iteration 20 — Verification, Rollback & Release Governance

Status: **Review**

## Scope

Iteration 20 is the final control layer before `Stable`. It does not declare the project Stable by itself. It supplies deterministic verification, controlled rollback and a release certification pipeline.

## Post-execution verification

`VerificationService` compares the expected state captured in the Change Request with the actual MODX resource after execution. Comparison is limited to expected fields so MODX-generated metadata such as timestamps does not produce false failures.

A mismatch is a hard failure. The queue handler marks the Change Request failed and raises a non-retryable exception to avoid repeating a potentially successful mutation.

## Rollback

`RollbackService` restores a resource from a pre-operation Snapshot through the same security/policy boundary. Restoration is limited to approved resource fields and captured TV values.

Automatic recreation of deleted resources is intentionally disabled because recreation can change identity and relationships. Such recovery requires an explicit future restore workflow.

## Release gates

`stable-gate.sh` is fail-closed. Runtime evidence is mandatory. Source-only success cannot certify `Stable`.

Required evidence includes static checks, unit/contract/security tests, real MODX integration, queue/concurrency tests, migration checks, artifact integrity and production security review.
