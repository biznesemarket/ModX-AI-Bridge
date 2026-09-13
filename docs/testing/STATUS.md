# Testing Status — Iterations 21–44

**Status: STABLE — `0.1.1` (`v0.1.1`), superseding `0.1.0`; all gates PASS on the CI runner**

Verified against a real Docker stack (MODX 3.2.2-pl, PHP 8.2.33, MySQL 8.0) and certified with a single
`AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh` run on GitHub Actions `ubuntu-latest`
(Docker + PHP + Composer + Node):

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
- SDK certification — PHP PASS; TypeScript PASS (Iteration 40, `TYPESCRIPT SDK: PASS`). Evidence:
  `docs/testing/iteration-35-sdk-certification.md`, `docs/testing/iteration-40-typescript-sdk-unblock.md`
- Upgrade/recovery drill — PASS. Evidence: `docs/testing/iteration-36-upgrade-recovery.md`
- Performance & limits — PASS (`scripts/performance-limits.php`: 256 KiB resource, 50 TVs, queue depth
  200, rate limiter, audit growth; per-op budgets). Evidence:
  `docs/testing/iteration-37-performance-limits.md`
- Release Candidate `0.1.0-rc1` — BUILT and content-verified; SHA-256 and metadata recorded
  (`docs/release/0.1.0-rc1.md`, `scripts/release-candidate.php`). Evidence:
  `docs/testing/iteration-38-release-candidate.md`
- Stable certification attempt (Iteration 39) — NOT STABLE (historical): gate exited 3 at the TypeScript SDK
  gate. Evidence: `docs/testing/iteration-39-stable-certification.md`
- Iteration 40 — TypeScript SDK gate cleared, single-command stable gate green on commit `642c681`
  (run `34715583238`). Evidence: `docs/testing/iteration-40-typescript-sdk-unblock.md`
- Iteration 41 — Stable `0.1.0` package finalized and re-certified on commit `160a659`
  (run `34716441523`); `STABLE certification gates passed.`; tag `v0.1.0`. Evidence:
  `docs/testing/iteration-41-stable-release.md`, `docs/release/0.1.0.md`
- Iteration 42 — CI hardening (`deterministic` job runs the `sdk` suite + TypeScript gate; Node 24 action
  runtimes; release-evidence artifact upload).
- Iteration 43 — reproducible transport packages (`PACKAGE REPRODUCIBILITY: PASS`, step 15 of test-modx);
  local and CI builds hash identically (`docs/development/transport-package.md`).
- Iteration 44 — Defect #34 fixed: the package now ships all 19 `aibridge_*` system settings; `0.1.1`
  certified on commit `db949b5` (run `34743692154`, `PACKAGE REPRODUCIBILITY: PASS`,
  `STABLE certification gates passed.`); tag `v0.1.1`. Evidence:
  `docs/testing/iteration-44-settings-packaging-0.1.1.md`, `docs/release/0.1.1.md`

No remaining gates: TypeScript SDK PASS, package reproducibility PASS, settings packaging fixed, and the
single-command `AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh` passes on a host/CI runner with
Docker. A missing runtime remains a failure of certification, not a pass.
