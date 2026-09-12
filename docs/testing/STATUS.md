# Testing Status — Iterations 21–34

**Status: Runtime certification in progress (target `0.1.0-rc1`)**

Verified against a real Docker stack (MODX 3.2.2-pl, PHP 8.2.33, MySQL 8.0), executed inside containers
because the Windows host has no PHP/Composer/POSIX shell:

- `composer validate --no-check-publish --strict` — PASS
- `composer lint` — PASS
- `composer verify-static-contract` — PASS
- `composer verify-generated-model` — PASS
- `phpunit --testsuite unit,contract,security` — PASS (58 tests, 188 assertions)
- `phpunit` with `MODX_ROOT` — PASS (113 tests, 1645 assertions, integration included)
- `scripts/verify-modx-runtime.php` — PASS
- `scripts/test-queue-concurrency.php` — PASS (forked race: exactly one winner)
- REST transport smoke over Apache — health 200, unauthenticated capabilities 401, ready 200
- Migration runner — idempotent (`skipped ... already applied` on rerun)
- Worker CLI `--once` — PASS
- Mutation E2E + verification/rollback — PASS (`tests/Integration/ResourceMutationE2ETest.php`, 6 tests).
  Evidence: `docs/testing/iteration-30-mutation-rollback.md`
- Approval workflow E2E — PASS (`tests/Integration/ApprovalWorkflowE2ETest.php`, 6 tests). Evidence:
  `docs/testing/iteration-31-approval-workflow.md`
- Multi-site isolation E2E — PASS (`tests/Integration/MultiSiteIsolationE2ETest.php`, 9 tests). Evidence:
  `docs/testing/iteration-32-multi-site-isolation.md`
- Security regression runtime — PASS (`tests/Integration/SecurityRegressionRuntimeTest.php`, 6 tests +
  `scripts/security-regression-http.php`). Evidence: `docs/testing/iteration-33-security-regression.md`
- Observability/readiness — PASS (`tests/Integration/ObservabilityRuntimeTest.php` + `SecretRedactorTest`):
  request-id propagation, structured errors, worker audit correlation, `/ready`, log redaction. Evidence:
  `docs/testing/iteration-34-observability.md`

Remaining gates before `Stable` (see `docs/release/FINAL-INTEGRATION-STATUS.md`): SDK certification,
upgrade/rollback drill, performance and limits, Release Candidate artifact and Stable tag. A missing runtime
is a failure of certification, not a pass.
