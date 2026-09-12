# Testing Status — Iterations 21–35

**Status: Runtime certification in progress (target `0.1.0-rc1`); TypeScript SDK BLOCKED (no Node)**

Verified against a real Docker stack (MODX 3.2.2-pl, PHP 8.2.33, MySQL 8.0), executed inside containers
because the Windows host has no PHP/Composer/POSIX shell:

- `composer validate --no-check-publish --strict` — PASS
- `composer lint` — PASS
- `composer verify-static-contract` — PASS
- `composer verify-generated-model` — PASS
- `phpunit --testsuite unit,contract,security` — PASS (58 tests, 188 assertions)
- `phpunit --testsuite sdk` — PASS (10 tests, 49 assertions)
- `phpunit` with `MODX_ROOT` — PASS (123 tests, 1840 assertions, integration + sdk included)
- `scripts/verify-modx-runtime.php` — PASS
- `scripts/test-queue-concurrency.php` — PASS (forked race: exactly one winner)
- REST transport smoke over Apache — health 200, unauthenticated capabilities 401, ready 200
- Migration runner — idempotent (`skipped ... already applied` on rerun)
- Worker CLI `--once` — PASS
- Mutation E2E + verification/rollback — PASS. Evidence: `docs/testing/iteration-30-mutation-rollback.md`
- Approval workflow E2E — PASS. Evidence: `docs/testing/iteration-31-approval-workflow.md`
- Multi-site isolation E2E — PASS. Evidence: `docs/testing/iteration-32-multi-site-isolation.md`
- Security regression runtime — PASS. Evidence: `docs/testing/iteration-33-security-regression.md`
- Observability/readiness — PASS. Evidence: `docs/testing/iteration-34-observability.md`
- SDK certification — PHP PASS (OpenAPI-aligned, 10 tests); TypeScript **BLOCKED** (`node`/`npm`
  unavailable, `tsc` build/tests not executed — not a PASS). Evidence:
  `docs/testing/iteration-35-sdk-certification.md`

Remaining gates before `Stable` (see `docs/release/FINAL-INTEGRATION-STATUS.md`): TypeScript SDK toolchain
(BLOCKED), upgrade/rollback drill, performance and limits, Release Candidate artifact and Stable tag. A
missing runtime is a failure of certification, not a pass.
