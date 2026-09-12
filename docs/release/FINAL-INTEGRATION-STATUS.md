# Final Integration & Stabilization — Status

Status: **RUNTIME CERTIFICATION IN PROGRESS**

Runtime environment used: Docker (MODX 3.2.2-pl, PHP 8.2.33, MySQL 8.0), local stack from
`docker-compose.yml`. Evidence: `docs/testing/iteration-21-26-runtime-certification.md`.

## Executed gates (PASS)

- `composer validate --no-check-publish --strict`
- `composer lint` (PHP syntax, all sources and tests)
- `composer verify-static-contract`
- `composer verify-generated-model`
- `composer test -- --testsuite unit,contract,security` — 51 tests, 125 assertions
- Full PHPUnit incl. integration against real MODX — 75 tests, 190 assertions
- Runtime verification (`scripts/verify-modx-runtime.php`) — namespace, xPDO model, service container,
  Manager menu, processor
- Queue concurrency harness with forked race — exactly one winner
- REST transport smoke over Apache (`/api/ai/v2/health` 200, `/api/ai/v2/capabilities` 401)
- Migration runner `status` / `migrate` idempotent against the real database
- Worker CLI `--once` graceful execution

## Required runtime gates not yet certified

1. Resource update/delete/preview/publish E2E through Manager and approval workflow (create is certified).
2. Verification mismatch → controlled failure → rollback drill.
3. Approval → execution → verification end-to-end certificate.
4. Multi-site isolation matrix across all entities (jobs certified; tokens/audit/snapshots/approvals pending).
5. Security regression matrix on runtime (rate limit, replay, oversized body, malformed JSON edge cases).
6. Observability/readiness/redaction runtime pass.
7. SDK contract certification (PHP + TypeScript).
8. Package upgrade and rollback drill.
9. Performance and limits certification.
10. Release Candidate artifact checksum and Stable certification tag.

Note: `scripts/test-modx.sh` and `scripts/certification/stable-gate.sh` wrapper scripts cannot run on the
Windows host (no POSIX shell/distro); every step they orchestrate was executed individually inside the
container with the results above.

## Stable certification rule

`Stable` MUST NOT be assigned until every required runtime gate passes. A missing runtime is a failure of
certification, not a pass.
