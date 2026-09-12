# Iteration 32 — Multi-Site Isolation Evidence

Environment: Docker Desktop on Windows, `docker compose` stack from `docker-compose.yml` — MODX 3.2.2-pl,
PHP 8.2.33, MySQL 8.0. Commands executed inside the `modx` container.

## Scope

Server-side profile isolation (Defect #26 follow-up): token authentication tied to an active profile, and
cross-profile access denied for Token, Job, Audit, Schema/Fingerprint, Snapshot, Change and Approval.

## Changes

- `TokenAuthenticator` now requires the token's `profile_id` to resolve to an **active** Profile; missing or
  inactive profiles are rejected (`token_profile_missing` / `profile_inactive`). Previously a token bound to
  a disabled profile still authenticated.
- `RollbackService` enforces snapshot ownership: when the principal carries a `profile_id`, a snapshot with a
  different `profile_id` is denied (`profile_mismatch`) before any mutation.
- `ChangeRequestService` transitions (`submit`/`approve`/`autoApprove`/`reject`) require the change to belong
  to the principal's profile when one is supplied (`Change request belongs to another profile.`).
- `ApprovalService::create`/`decide` validate that the bound change belongs to the acting profile.
- `ChangeExecutionService::dispatchApproved` rejects execution of another profile's change.

All checks are server-side and ignore client-supplied `profile_id` as proof of access; an unscoped manager
(`profile_id = 0`) keeps global administration, while token principals are always scoped.

## Evidence

- `MODX_ROOT=/var/www/html vendor/bin/phpunit --filter MultiSiteIsolationE2ETest` → **OK (9 tests, 35 assertions)**:
  1. authentication principal carries the token's owning profile; 2. issuance without a profile is rejected;
  3. a token for a disabled profile is rejected and works again once re-activated;
  4. job status is readable by the owner (200) and returns 404 for another profile;
  5. `/profiles` returns only the token's own profile;
  6. snapshot rollback from another profile is denied (`profile_mismatch`) and the resource is unchanged,
     while the owner's rollback succeeds;
  7. change submit/approval/approve/execute across profiles are all denied;
  8. audit rows carry the owning `profile_id` and no foreign-profile audit row exists for the change;
  9. site schema/fingerprint remain site-level and identical for both profile tokens.
- `MODX_ROOT=/var/www/html vendor/bin/phpunit --testsuite unit,contract,security` → **OK (57 tests, 181 assertions)**.
- `MODX_ROOT=/var/www/html vendor/bin/phpunit` → **OK (102 tests, 363 assertions)**.
- After rebuild + package install: `scripts/verify-modx-runtime.php` → PASS; `GET /api/ai/v2/capabilities` → 401;
  `composer lint` / `verify-static-contract` / `verify-generated-model` → PASS.

## Notes / remaining

- Resources themselves are MODX-global; isolation is enforced on the Bridge entities that carry `profile_id`
  (tokens, jobs, snapshots, changes/approvals, audit, idempotency, rate limits).
- Security regression matrix on runtime, observability/redaction, SDK, upgrade drill, performance, RC and the
  Stable tag remain for Iterations 33–39.
