# Upgrade and Rollback Strategy

## Upgrade

1. Verify the compatibility matrix.
2. Create and verify a database backup.
3. Record current Bridge package/version and migration level.
4. Install the new Transport Package.
5. Run migrations in deterministic order.
6. Invalidate caches.
7. Restart workers.
8. Run readiness and smoke tests.
9. Record the release.

## Rollback

Rollback is split into two cases.

### Application-only rollback

If no incompatible database migration has been applied, reinstall the previous known-good Transport Package and restart workers.

### Database rollback

If a migration changed the schema/data and the migration is not explicitly reversible, restore the verified database backup and reinstall the known-good application package. Do not attempt an ad-hoc reverse migration in production.

## Release metadata

Each release should record:

- package signature;
- package SHA-256;
- Bridge version/release;
- MODX version;
- migration level;
- deployment timestamp;
- operator/change reference.

## Executable drill

`scripts/recovery-drill.sh` (run inside the MODX container) exercises this runbook end to end on the
disposable Docker stack: it applies baseline migrations, records a checksummed database backup, simulates an
additive schema upgrade, runs the runtime smoke test, restores the backup and verifies that the schema,
data and migration ledger returned to the baseline. A backup is not considered verified until this restore
test passes.
