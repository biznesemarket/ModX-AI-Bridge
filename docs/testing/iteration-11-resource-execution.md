# Iteration 11 Verification

Status: **Review**

Static verification must cover:

- PHP syntax;
- application/service dependency boundaries;
- operation allowlist;
- idempotency requirement for mutations;
- delete default-deny policy;
- snapshot creation before mutation;
- transaction rollback path;
- cache invalidation boundary;
- audit calls;
- MCP and REST reuse of the same execution services.

Runtime verification requires a real MODX 3.2.x installation with MySQL/MariaDB. It must execute create, update, preview, publish and delete against disposable resources and verify database state before and after each operation.
