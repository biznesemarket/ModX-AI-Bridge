# Iteration 18 — Resource Explorer Testing

Status: Review

## Static gates

- PHP syntax check for all PHP sources.
- Verify Manager processors are namespaced class-based processors.
- Verify no Manager JavaScript performs direct database writes.
- Verify mutation UI calls `resource-operation` only.

## Security cases

1. Manager without `aibridge_manage` is rejected.
2. Unsupported operation is rejected.
3. Delete remains blocked when `allow_resource_delete=0`.
4. Publish without approval remains blocked.
5. Manager channel does not weaken token/IP security for external API principals.
6. Token hashes and secrets are not exposed by Resource Explorer.

## Functional cases

- Browse root resources.
- Search by title/alias/description.
- Filter by Template.
- Filter by TV name/value.
- Open resource details.
- Retrieve Content Contract.
- Run Content QA.
- Preview without persistence.
- Dispatch update/delete/publish jobs.
- Observe job progress.
- Compare two fingerprint snapshots.

## Runtime requirement

Final acceptance requires MODX 3.2.x, MySQL/MariaDB, installed package, Manager login, generated xPDO model metadata and a running Queue Worker.
