# Testing Status — Iterations 21–32

**Status: Runtime certification in progress (target `0.1.0-rc1`)**

Verified against a real Docker stack (MODX 3.2.2-pl, PHP 8.2.33, MySQL 8.0), executed inside containers
because the Windows host has no PHP/Composer/POSIX shell:

- `composer validate --no-check-publish --strict` — PASS
- `composer lint` — PASS
- `composer verify-static-contract` — PASS
- `composer verify-generated-model` — PASS
- `phpunit --testsuite unit,contract,security` — PASS (57 tests, 181 assertions)
- `phpunit` with `MODX_ROOT` — PASS (102 tests, 363 assertions, integration included)
- `scripts/verify-modx-runtime.php` — PASS
- `scripts/test-queue-concurrency.php` — PASS (forked race: exactly one winner)
- REST transport smoke over Apache — health 200, unauthenticated capabilities 401
- Migration runner — idempotent (`skipped ... already applied` on rerun)
- Worker CLI `--once` — PASS
- Mutation E2E + verification/rollback — PASS (`tests/Integration/ResourceMutationE2ETest.php`, 6 tests).
  Evidence: `docs/testing/iteration-30-mutation-rollback.md`
- Approval workflow E2E — PASS (`tests/Integration/ApprovalWorkflowE2ETest.php`, 6 tests). Evidence:
  `docs/testing/iteration-31-approval-workflow.md`
- Multi-site isolation E2E — PASS (`tests/Integration/MultiSiteIsolationE2ETest.php`, 9 tests: active-profile
  auth, job/profile scoping, snapshot-rollback IDOR denied, change/approval profile guards, audit
  ownership, site-level schema/fingerprint). Evidence: `docs/testing/iteration-32-multi-site-isolation.md`

Remaining gates before `Stable` (see `docs/release/FINAL-INTEGRATION-STATUS.md`): security regression runtime
matrix, observability/redaction pass, SDK certification, upgrade/rollback drill, performance and limits,
Release Candidate artifact and Stable tag. A missing runtime is a failure of certification, not a pass.
