# Execution Pipeline

Every mutating operation follows this logical sequence:

```text
Request
 → Authentication
 → Authorization
 → Policy
 → Idempotency
 → Content Contract
 → QA
 → Snapshot
 → Transaction
 → MODX/xPDO
 → Cache Invalidation
 → Audit
 → Commit / Rollback
```

`resource.delete` intentionally skips content QA because deletion does not create a new content representation. It still requires authentication, authorization, policy, idempotency, snapshot, transaction, cache invalidation and audit.

A failed transaction is rolled back when the database connection reports an active transaction. A pre-mutation snapshot is retained for operational recovery workflows.
