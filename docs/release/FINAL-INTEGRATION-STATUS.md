# Final Integration & Stabilization — Status

Status: **RUNTIME CERTIFICATION IN PROGRESS**

Runtime environment used: Docker (MODX 3.2.2-pl, PHP 8.2.33, MySQL 8.0), local stack from
`docker-compose.yml`. Evidence: `docs/testing/iteration-21-26-runtime-certification.md`.

## Executed gates (PASS)

- `composer validate --no-check-publish --strict`
- `composer lint` (PHP syntax, all sources and tests)
- `composer verify-static-contract`
- `composer verify-generated-model`
- `composer test -- --testsuite unit,contract,security` — 57 tests, 181 assertions
- Full PHPUnit incl. integration against real MODX — 102 tests, 363 assertions
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

## Required runtime gates not yet certified

1. Security regression matrix on runtime (rate limit, replay, oversized body, malformed JSON edge cases).
2. Observability/readiness/redaction runtime pass.
3. SDK contract certification (PHP + TypeScript).
4. Package upgrade and rollback drill.
5. Performance and limits certification.
6. Release Candidate artifact checksum and Stable certification tag.

Note: `scripts/test-modx.sh` and `scripts/certification/stable-gate.sh` wrapper scripts cannot run on the
Windows host (no POSIX shell/distro); every step they orchestrate was executed individually inside the
container with the results above.

## Stable certification rule

`Stable` MUST NOT be assigned until every required runtime gate passes. A missing runtime is a failure of
certification, not a pass.
