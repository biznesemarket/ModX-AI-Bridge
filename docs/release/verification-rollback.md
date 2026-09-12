# Verification & Rollback

Every approved mutation has an expected state captured in the Change Request. After execution, `VerificationService` reads the actual MODX resource and compares the relevant state recursively.

A verification mismatch changes the Change Request to `failed` and raises a non-retryable job failure. This prevents a destructive retry when the MODX operation may actually have succeeded.

Rollback uses the immutable Snapshot created before an update/delete/publish operation. Restoration is restricted to the same resource and to the Bridge's allowed resource fields. Secret values are not reconstructed from redacted snapshots.

Automatic recreation of a deleted resource is deliberately disabled in Iteration 20; this avoids silently changing resource identity, parentage or related MODX state. Such recovery requires an explicit future restore workflow.
