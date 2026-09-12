# Migration Operations

Bridge migrations live under `core/components/aibridge/migrations/` and are applied in lexical/version order.

## Single source of truth

The xPDO schema (`core/components/aibridge/schema/aibridge.mysql.schema.xml`) is the single source of
truth for table structure:

- a fresh install creates all 12 Bridge tables through the package PHP resolver
  (`_build/resolvers/install-model.resolver.php`) using `createObjectContainer()` against the generated
  model metadata;
- SQL migrations are **only** for upgrades of existing installations and must always match the schema.

Both mechanisms must never create the same table: the resolver runs first on a fresh install, and a
migration must not attempt to re-create an existing table with a different column set.

SQL files may use either `[[+prefix]]` or `{PREFIX}`; the runner substitutes both with the value of the
MODX `table_prefix` setting before execution.

`001_initial.sql` mirrors the current xPDO schema exactly; this is verified by applying the migrations into a
scratch database and diffing `mysqldump --no-data` output against a resolver-created installation.

## Migration rules

- each migration has a unique numeric prefix;
- migrations are idempotent where practical;
- destructive changes require an explicit migration note and backup requirement;
- application code must tolerate the migration boundary required for rolling deployment;
- migration failures stop deployment;
- migration status is persisted outside the application log (`{prefix}aibridge_migrations`).

The included runner supports `status` and `migrate` commands and records applied migration filenames in
the Bridge migration table. Each migration runs inside a transaction; on failure the transaction is
rolled back and the runner exits non-zero.
