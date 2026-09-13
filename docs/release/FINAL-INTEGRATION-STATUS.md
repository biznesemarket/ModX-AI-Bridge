# Final Integration & Stabilization — Status

Status: **STABLE** — current release `0.9.1` (tag `v0.9.1`), superseding `0.9.0`, `0.8.0`, `0.7.1`, `0.7.0`,
`0.6.0`, `0.5.0`, `0.4.0`, `0.3.0`, `0.2.0`, `0.1.3`, `0.1.2`, `0.1.1` and `0.1.0`. Evidence:
`docs/release/stable-status.md`, `docs/release/0.9.1.md`, `docs/release/0.9.0.md`, `docs/release/0.8.0.md`,
`docs/release/0.7.1.md`, `docs/release/0.7.0.md`, `docs/release/0.6.0.md`, `docs/release/0.5.0.md`,
`docs/release/0.4.0.md`, `docs/release/0.3.0.md`, `docs/release/0.2.0.md`, `docs/release/0.1.3.md`,
`docs/release/0.1.2.md`, `docs/release/0.1.1.md`, `docs/release/0.1.0.md`,
`docs/testing/iteration-61-manager-coverage.md`.

Runtime environment used: Docker (MODX 3.2.2-pl, PHP 8.2.33, MySQL 8.0), local stack from
`docker-compose.yml`, plus a GitHub Actions `ubuntu-latest` runner (Docker + PHP + Composer + Node) for the
single-command gate. Evidence: `docs/testing/iteration-21-26-runtime-certification.md`.

## Executed gates (PASS)

- `composer validate --no-check-publish --strict`
- `composer lint` (PHP syntax, all sources and tests)
- `composer verify-static-contract`
- `composer verify-generated-model`
- `composer test -- --testsuite unit,contract,security` — 58 tests, 188 assertions
- `composer test -- --testsuite sdk` — 10 tests, 49 assertions
- Full PHPUnit incl. integration against real MODX — 123 tests, ~2128 assertions
- Runtime verification (`scripts/verify-modx-runtime.php`) — namespace, xPDO model, service container,
  Manager menu, processor
- Queue concurrency harness with forked race — exactly one winner
- REST transport smoke over Apache (`/api/ai/v2/health` 200, `/api/ai/v2/capabilities` 401)
- Migration runner `status` / `migrate` idempotent against the real database
- Worker CLI `--once` graceful execution
- Mutation E2E + verification/rollback (`tests/Integration/ResourceMutationE2ETest.php`) — Manager
  update/preview/delete/publish through approvals, snapshot/audit, controlled mismatch →
  `NonRetryableJobException`, rollback restores fields/TVs, deleted-resource recreation refused
- Approval workflow E2E (`tests/Integration/ApprovalWorkflowE2ETest.php`) — `draft→submit→reject`,
  approval↔change binding, execute-only-approved, approve→execute→verify→audit, terminal states,
  publish without approval denied
- Multi-site isolation E2E (`tests/Integration/MultiSiteIsolationE2ETest.php`) — active-profile token auth,
  job/profile scoping, snapshot-rollback IDOR denied, change/approval profile guards, audit ownership,
  site-level schema/fingerprint
- Security regression runtime (`tests/Integration/SecurityRegressionRuntimeTest.php` +
  `scripts/security-regression-http.php`) — auth bypass, scope escalation, profile escape, rate limit,
  idempotency replay/conflict, secret leakage, malformed JSON 400 and oversized body 413
- Observability/readiness (`tests/Integration/ObservabilityRuntimeTest.php` + `SecretRedactorTest`) —
  inbound request-id propagation/echo, structured error envelopes, worker audit `request_id`, public
  `GET /ready` and free-text log redaction
- PHP SDK certification (`sdk/php/tests`, `--testsuite sdk`) — idempotency/auth/job contracts and
  OpenAPI alignment (every SDK path documented, mutation `202` + `Idempotency-Key`)
- Upgrade/recovery drill (`scripts/recovery-drill.sh`, wired into `scripts/test-modx.sh`) — baseline
  migrate, checksummed database backup, additive schema upgrade, smoke, restore and baseline verification
- Performance & limits (`scripts/performance-limits.php`) — 256 KiB resource, 50 TVs, queue depth 200,
  rate-limiter throughput and audit growth measured against per-op budgets
- Release Candidate `0.1.0-rc1` (`scripts/release-candidate.php`) — built, contents verified, SHA-256 and
  release metadata recorded (`docs/release/0.1.0-rc1.md`)

## Previously blocking runtime gates (resolved)

1. TypeScript SDK certification — **PASS**: added the missing `package-lock.json`, fixed NodeNext import
   resolution and idempotency-key typing, and set the executable bit; CI prints `TYPESCRIPT SDK: PASS`.
2. Stable certification tag — `v0.1.0` (commit `160a659`, run `34716441523`) and the current `v0.1.1`
   (commit `db949b5`, run `34743692154`), each created after
   `AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh` printed `STABLE certification gates passed.`
3. System settings packaging — Defect #34 fixed in `0.1.1`: `0.1.0` and earlier did not install the
   19 `aibridge_*` settings.
4. Reproducible transport packages — Iteration 43; `PACKAGE REPRODUCIBILITY: PASS` is part of the runtime gate.

Note: the Windows host has no PHP/Composer, so the single-command gate is executed on GitHub Actions
`ubuntu-latest` (Docker + PHP + Composer + Node); the same gate's individual steps were also executed against
the local Docker stack throughout iterations 30–38.

## Stable certification rule

`Stable` MUST NOT be assigned until every required runtime gate passes. A missing runtime is a failure of
certification, not a pass.
