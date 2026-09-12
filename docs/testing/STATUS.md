# Testing Status — Iterations 21–39

**Status: NOT STABLE — Release Candidate `0.1.0-rc1`; TypeScript SDK gate BLOCKED; no `v0.1.0` tag**

Verified against a real Docker stack (MODX 3.2.2-pl, PHP 8.2.33, MySQL 8.0), executed inside containers
because the Windows host has no PHP/Composer/POSIX shell:

- `composer validate --no-check-publish --strict` — PASS
- `composer lint` — PASS
- `composer verify-static-contract` — PASS
- `composer verify-generated-model` — PASS
- `phpunit --testsuite unit,contract,security` — PASS (58 tests, 188 assertions)
- `phpunit --testsuite sdk` — PASS (10 tests, 49 assertions)
- `phpunit` with `MODX_ROOT` — PASS (123 tests, integration + sdk included)
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
- SDK certification — PHP PASS; TypeScript **BLOCKED** (no `node`/`npm`). Evidence:
  `docs/testing/iteration-35-sdk-certification.md`
- Upgrade/recovery drill — PASS. Evidence: `docs/testing/iteration-36-upgrade-recovery.md`
- Performance & limits — PASS (`scripts/performance-limits.php`: 256 KiB resource, 50 TVs, queue depth
  200, rate limiter, audit growth; per-op budgets). Evidence:
  `docs/testing/iteration-37-performance-limits.md`
- Release Candidate `0.1.0-rc1` — BUILT and content-verified; SHA-256 and metadata recorded
  (`docs/release/0.1.0-rc1.md`, `scripts/release-candidate.php`). Evidence:
  `docs/testing/iteration-38-release-candidate.md`
- Stable certification attempt — **NOT STABLE**: `AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh`
  exits 3 at the TypeScript SDK gate (`node`/`npm` unavailable); no `v0.1.0` tag created. Evidence:
  `docs/testing/iteration-39-stable-certification.md`, `docs/release/stable-status.md`

Remaining gates before `Stable`: TypeScript SDK toolchain (BLOCKED), and a single-command run of
`stable-gate.sh` on a host/CI runner with Docker (the in-container run cannot execute `composer test-modx`).
A missing runtime is a failure of certification, not a pass.
