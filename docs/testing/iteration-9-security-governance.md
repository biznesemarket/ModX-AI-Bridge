# Iteration 9 — Security & Governance

Status: **Review**

## Scope

Production-oriented security boundary for AI-originated MODX operations.

Implemented: token hashing and expiry, explicit scopes, IP allowlist, atomic DB-backed rate limiting, recursive secret redaction, persistent audit events, idempotency persistence, operation policy checks, approval gate for publication, and a single security decision pipeline.

## Non-goals

This iteration does not claim complete production hardening. TLS termination, WAF/reverse-proxy controls, database backup policy, distributed job locking and external SIEM delivery remain deployment-level concerns.

## Acceptance criteria

- No plaintext token persistence.
- No privileged operation without scope.
- Disabled dangerous operations remain blocked even with wildcard scope.
- Publication can require an approval identifier.
- Reused idempotency key with a different payload is rejected.
- Audit payloads are recursively redacted.
- Rate limiting is shared through the database rather than process-local memory.
