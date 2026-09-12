# Iteration 30 — Mutation E2E, Verification Mismatch & Rollback Evidence

Environment: Docker Desktop on Windows, `docker compose` stack from `docker-compose.yml` — MODX 3.2.2-pl,
PHP 8.2.33, MySQL 8.0. Commands executed inside the `modx` container (workdir `/workspace/modx-ai-bridge`);
the Windows host has no PHP/Composer/POSIX shell.

## Scope

Mutation cycle through the Manager service boundary (`ResourceOperationService`), the queue worker
(`Worker` + `ResourceExecutionJobHandler`), post-execution verification (`VerificationService`) and
snapshot rollback (`RollbackService`, `ResourceRollbackProcessor`).

## Changes

- `ResourceRollbackProcessor` now extends `AdminProcessor` (manager authentication + `aibridge_manage`
  permission) and builds a proper manager principal (`type=manager`, `manager_authorized=true`,
  `scopes=['*']`, `profile_id`) with `channel=manager`. Previously it produced a principal without
  `manager_authorized`/`scopes` and a request without `channel`, so the rollback security pipeline denied
  every Manager rollback (IP check fail-closed and `insufficient_scope`) — Defect #7 remained reachable
  through the processor despite the `Authorization` mapping existing.
- `ChangeRequestService` normalizes `tvs` keys (`tv:` prefix stripped) and, for `resource.publish`,
  augments the expected state with `published=1` and a non-empty `publishedon` marker — Defect #25.
- `VerificationService` gained `PRESENT` sentinel semantics: a marker expected value passes only when the
  actual value is set and non-empty (used for non-deterministic `publishedon`). Scalar comparison treats a
  boolean actual (`published`) as equal to its `0`/`1` expected representation, because xPDO coerces
  boolean resource fields.

## Evidence

- `MODX_ROOT=/var/www/html vendor/bin/phpunit --filter ResourceMutationE2ETest` → **OK (6 tests, 41 assertions)**:
  1. Manager update → auto-approval → queue → snapshot → verification `completed` → audit `resource_execution`.
  2. Manager preview is synchronous, non-persistent and returns the candidate content.
  3. Manager delete returns `pending_approval`, executes after approval, hard-deletes the resource;
     rollback of the delete snapshot is refused (`automatic recreation ... disabled`).
  4. Manager publish requires approval, then sets `published=1`/`publishedon` and verifies the published state.
  5. Controlled expected/actual mismatch → `NonRetryableJobException` → job `failed` (no retry) and change `failed`.
  6. Rollback restores resource fields and Template Variable values from the pre-mutation snapshot.
- `MODX_ROOT=/var/www/html vendor/bin/phpunit --testsuite unit,contract,security` → **OK (55 tests, 129 assertions)**.
- `MODX_ROOT=/var/www/html vendor/bin/phpunit` → **OK (85 tests, 235 assertions)**.
- `composer lint` → no syntax errors; `composer verify-static-contract` → PASS;
  `composer verify-generated-model` → PASS; `composer validate --no-check-publish --strict` → valid;
  `scripts/test-queue-concurrency.php` → PASS (forked race: exactly one winner).
- After `php _build/build.php` + `scripts/install-package.php`: `scripts/verify-modx-runtime.php` → PASS;
  Apache smoke `GET /api/ai/v2/health` → 200, `GET /api/ai/v2/capabilities` → 401.

## Notes / remaining

- xPDO `fromArray()` ignores composite primary-key fields unless `$setPrimary` is passed; test fixtures set
  `modTemplateVarTemplate` keys with `set()`.
- `modResource::remove()` performs a hard delete in MODX 3.2, so `verifyDeleted` observes a missing row and
  rollback must refuse recreation (by design).
- Approval workflow matrix (draft→submit→reject/approve) and the Manager processor-level authentication
  path remain for Iteration 31.
