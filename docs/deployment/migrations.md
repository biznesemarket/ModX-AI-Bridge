# Migration Operations

Bridge migrations live under `core/components/aibridge/migrations/` and are applied in lexical/version order.

Migration rules:

- each migration has a unique numeric prefix;
- migrations are idempotent where practical;
- destructive changes require an explicit migration note and backup requirement;
- application code must tolerate the migration boundary required for rolling deployment;
- migration failures stop deployment;
- migration status is persisted outside the application log.

The included runner supports `status` and `migrate` commands and records applied migration filenames in the Bridge migration table.
