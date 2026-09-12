# Iteration 36 — Upgrade / Recovery Drill Evidence

Environment: Docker Desktop on Windows, `docker compose` stack from `docker-compose.yml` — MODX 3.2.2-pl,
PHP 8.2.33, MySQL 8.0. Drill executed inside the `modx` container against the real database.

## Scope

Executable upgrade/recovery drill (`scripts/recovery-drill.sh`), not just a document:
baseline migrate → database backup → schema upgrade simulation → smoke → rollback (restore) → verify.

## Changes

- Added `scripts/recovery-drill.sh`: dumps the MODX database (`mysqldump --single-transaction
  --no-tablespaces --add-drop-table`, SHA-256 recorded), applies baseline migrations, simulates an upgrade
  with an additive schema change, runs the runtime smoke, restores the dump and verifies the schema, data
  and migration ledger returned to baseline.
- Wired the drill into `scripts/test-modx.sh` (step 12) so the full runtime gate executes it.
- `docs/deployment/upgrade-rollback.md` and `docs/operations/backup-restore.md` reference the executable
  drill.

## Evidence

`MODX_ROOT=/var/www/html bash scripts/recovery-drill.sh` → **RECOVERY DRILL: PASS**:

```text
== 1. Baseline migrations ==
skipped 001_initial.sql (already applied)
skipped 002_approval_workflow.sql (already applied)
  migration: applied 001_initial.sql 2026-09-12 19:31:29
  migration: applied 002_approval_workflow.sql 2026-09-12 19:31:29
== 2. Database backup ==
7c604e4b39c54b5fc53a9d10a9de872c3294a46a1340cb411eef410734069db6  /tmp/aibridge-recovery-*.sql
backup_size=1183131 bytes
== 3. Pre-upgrade state ==
profiles=81
== 4. Upgrade simulation (schema change) ==
schema upgraded: modx_aibridge_profiles.recovery_drill_marker added
== 5. Smoke after upgrade ==
MODX runtime verification: PASS ...
== 6. Rollback (restore backup) ==
== 7. Verify rollback ==
RECOVERY DRILL: PASS (schema change rolled back by restore; profiles 81 -> 81)
```

Post-drill regression suite:

- `phpunit --testsuite unit,contract,security` → **OK (58 tests, 188 assertions)**.
- `phpunit` with `MODX_ROOT` → **OK (123 tests, 1984 assertions)**.
- REST smoke after restore: health 200, ready 200, capabilities 401.

## Notes / remaining

- The drill deliberately mutates the live schema and restores the whole database; it is intended for the
  disposable Docker stack, not production.
- The first run surfaced that this stack's migration ledger had never been created (the tables were created
  by the package resolver); the drill now establishes the baseline before backing up, so
  `migrate status` is deterministic.
- Performance/limits, Release Candidate artifact and the Stable tag remain for Iterations 37–39.
- TypeScript SDK remains BLOCKED (no Node toolchain).
