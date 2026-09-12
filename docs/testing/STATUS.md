# Testing Status — Iterations 21–29

**Status: Runtime certification in progress (target `0.1.0-rc1`)**

Verified against a real Docker stack (MODX 3.2.2-pl, PHP 8.2.33, MySQL 8.0), executed inside containers
because the Windows host has no PHP/Composer/POSIX shell:

- `composer validate --no-check-publish --strict` — PASS
- `composer lint` — PASS
- `composer verify-static-contract` — PASS
- `composer verify-generated-model` — PASS
- `phpunit --testsuite unit,contract,security` — PASS (51 tests, 125 assertions)
- `phpunit` with `MODX_ROOT` — PASS (75 tests, 190 assertions, integration included)
- `scripts/verify-modx-runtime.php` — PASS
- `scripts/test-queue-concurrency.php` — PASS (forked race: exactly one winner)
- REST transport smoke over Apache — health 200, unauthenticated capabilities 401
- Migration runner — idempotent (`skipped ... already applied` on rerun)
- Worker CLI `--once` — PASS

Remaining gates before `Stable` (see `docs/release/FINAL-INTEGRATION-STATUS.md`): update/delete/preview/publish
E2E through approvals, verification-mismatch rollback drill, full multi-site matrix, security regression
runtime matrix, observability/redaction pass, SDK certification, upgrade/rollback drill, performance and
limits, Release Candidate artifact and Stable tag. A missing runtime is a failure of certification, not a pass.
