# Production Deployment Checklist

## Before deployment

- [ ] Target MODX version is supported by the release.
- [ ] PHP extensions and database version are verified.
- [ ] Transport Package checksum is recorded.
- [ ] Database backup completed and restore procedure is known to work.
- [ ] `core/config/config.inc.php` and Bridge secrets are excluded from source control.
- [ ] TLS is terminated correctly and HTTP-to-HTTPS policy is enforced.
- [ ] API token scopes and IP allowlists are reviewed.
- [ ] Dangerous operations remain disabled unless explicitly approved.
- [ ] Worker capacity and queue limits are defined.
- [ ] Monitoring/log aggregation is available.

## Deployment

- [ ] Put the site in the agreed maintenance/deployment mode if required.
- [ ] Install/upgrade the Transport Package.
- [ ] Run migrations using the migration runner.
- [ ] Clear/rebuild relevant MODX caches.
- [ ] Start/restart workers using the new application version.
- [ ] Verify readiness.
- [ ] Verify authenticated API smoke test.
- [ ] Verify MCP discovery if enabled.
- [ ] Verify queue claim and completion.
- [ ] Verify audit records are being written.

## After deployment

- [ ] Confirm no migration failures.
- [ ] Confirm no repeated worker crashes.
- [ ] Confirm queue depth is stable.
- [ ] Confirm API error rate is normal.
- [ ] Confirm no secrets appear in logs.
- [ ] Record release version, package checksum, migration level, and deployment timestamp.

## Rollback trigger

Rollback is required when schema migration fails, readiness cannot be restored, critical API security invariants fail, or application behavior is materially corrupted.
