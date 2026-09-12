# Recovery and Rollback

The execution engine creates a snapshot before an update, delete or publish operation. The current iteration uses the snapshot as a durable recovery artifact; it does not automatically restore a resource after an application-level failure.

Automatic rollback is limited to the active database transaction. External side effects are deliberately not assumed to be transactional.

A future recovery subsystem should provide:

1. snapshot inspection;
2. explicit operator approval;
3. resource restoration;
4. TV restoration;
5. cache invalidation;
6. audit of the recovery operation.
