# Observability

Status: Draft

The Bridge operations model separates three concerns:

1. **Health** — whether the component can answer basic requests.
2. **Readiness** — whether MODX, database, service container and Bridge namespace are available.
3. **Audit** — who initiated an operation, which profile it targeted, what operation was requested and what request/job/resource identifiers were involved.

The Manager Console is a presentation layer over these operational signals; it is not the source of truth.

## Redaction

Sensitive fields must be redacted before they reach Manager UI, API responses, audit context or logs. In particular, token plaintext and token hashes are not displayed.
