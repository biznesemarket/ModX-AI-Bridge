#!/usr/bin/env bash
#
# Upgrade / recovery drill, executed inside the MODX container against the real
# MySQL database:
#   baseline migrate -> backup -> upgrade (schema change) -> smoke -> rollback (restore) -> verify
#
# A backup is not considered verified until a restore test has succeeded, so the
# drill mutates the schema and proves the restore brings the database back to the
# baseline (schema, data and migration ledger).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
export MODX_ROOT="${MODX_ROOT:-/var/www/html}"

DB_HOST="${AIBRIDGE_DB_HOST:-db}"
DB_USER="${AIBRIDGE_DB_USER:-modx}"
DB_NAME="${AIBRIDGE_DB_NAME:-modx}"
DB_PREFIX="${AIBRIDGE_DB_PREFIX:-modx_}"
export MYSQL_PWD="${AIBRIDGE_DB_PASSWORD:-modx}"

DB=(mysql --skip-ssl -h "$DB_HOST" -u "$DB_USER" "$DB_NAME")
DUMP=(mysqldump --skip-ssl -h "$DB_HOST" -u "$DB_USER" --single-transaction --no-tablespaces --add-drop-table "$DB_NAME")
PROFILES_TABLE="${DB_PREFIX}aibridge_profiles"
MARKER_COLUMN="recovery_drill_marker"

BACKUP="$(mktemp /tmp/aibridge-recovery-XXXXXX.sql)"
trap 'rm -f "$BACKUP" "$BACKUP.sha256"' EXIT

column_count() {
  "${DB[@]}" -N -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='${DB_NAME}' AND table_name='${PROFILES_TABLE}' AND column_name='${MARKER_COLUMN}'"
}

echo "== 1. Baseline migrations =="
php "${ROOT}/scripts/migrations/migrate.php" migrate
php "${ROOT}/scripts/migrations/migrate.php" status | sed 's/^/  migration: /'

echo "== 2. Database backup =="
"${DUMP[@]}" > "$BACKUP"
test -s "$BACKUP"
sha256sum "$BACKUP" | tee "$BACKUP.sha256"
echo "backup_size=$(wc -c < "$BACKUP") bytes"

echo "== 3. Pre-upgrade state =="
PROFILES_BEFORE=$("${DB[@]}" -N -e "SELECT COUNT(*) FROM ${PROFILES_TABLE}")
echo "profiles=${PROFILES_BEFORE}"

echo "== 4. Upgrade simulation (schema change) =="
if [ "$(column_count)" != "0" ]; then
  "${DB[@]}" -e "ALTER TABLE ${PROFILES_TABLE} DROP COLUMN ${MARKER_COLUMN}"
fi
"${DB[@]}" -e "ALTER TABLE ${PROFILES_TABLE} ADD COLUMN ${MARKER_COLUMN} INT NULL"
test "$(column_count)" = "1"
echo "schema upgraded: ${PROFILES_TABLE}.${MARKER_COLUMN} added"

echo "== 5. Smoke after upgrade =="
php "${ROOT}/scripts/verify-modx-runtime.php"

echo "== 6. Rollback (restore backup) =="
"${DB[@]}" < "$BACKUP"

echo "== 7. Verify rollback =="
test "$(column_count)" = "0"
PROFILES_AFTER=$("${DB[@]}" -N -e "SELECT COUNT(*) FROM ${PROFILES_TABLE}")
test "$PROFILES_AFTER" = "$PROFILES_BEFORE"
php "${ROOT}/scripts/migrations/migrate.php" status | grep -q "^applied "
php "${ROOT}/scripts/verify-modx-runtime.php" >/dev/null

echo "RECOVERY DRILL: PASS (schema change rolled back by restore; profiles ${PROFILES_BEFORE} -> ${PROFILES_AFTER})"
