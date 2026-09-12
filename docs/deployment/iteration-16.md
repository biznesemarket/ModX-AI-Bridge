# Iteration 16 — Deployment & Production Operations

Status: **Review**

Iteration 16 defines the production deployment boundary for ModX AI Bridge. It does not claim a production deployment is safe until the target MODX installation, database, secrets, TLS termination, backup process, and CI gates have been verified.

## Scope

- production-oriented container definitions;
- CI/CD validation and transport-package release;
- database migration discipline;
- upgrade and rollback strategy;
- health/readiness checks;
- worker deployment;
- secret handling rules;
- structured logging and observability contract;
- backup/restore runbook;
- production deployment checklist.

## Release flow

```text
commit
  ↓
static checks
  ↓
unit / contract / security tests
  ↓
MODX integration tests
  ↓
build Transport Package
  ↓
package verification
  ↓
release artifact
  ↓
backup + maintenance window
  ↓
install/upgrade package
  ↓
migrations
  ↓
readiness check
  ↓
smoke tests
  ↓
release accepted
```

## Production invariants

1. Secrets are supplied at runtime and are never committed to Git.
2. Database backup must exist before a destructive upgrade.
3. Transport Package version and application version are recorded in the release metadata.
4. Migrations are forward-only by default; rollback is performed by restoring the database/application artifact when a migration is not safely reversible.
5. Readiness must fail closed if required MODX configuration or database connectivity is unavailable.
6. Workers use the same Bridge application version as the web/API process.
7. Health endpoints do not disclose credentials, tokens, full MODX configuration, or database connection strings.
8. Production package installation is gated by CI evidence, not by a successful local build alone.
