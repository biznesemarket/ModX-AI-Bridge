# Security & Governance

Status: **Review**

Iteration 9 establishes the security decision boundary for every operation that can affect MODX state.

## Security pipeline

```text
HTTP request
  -> HTTPS check
  -> request size / syntax validation
  -> Bearer authentication
  -> IP allowlist
  -> rate limit
  -> scope authorization
  -> operation policy
  -> approval requirement
  -> idempotency
  -> audit
  -> application operation
```

The default posture is fail-closed for privileged operations.

## Tokens

Only a SHA-256 hash is persisted. The plaintext token is returned once when it is issued and must not be stored by Bridge or written to audit logs.

Token scopes are explicit. `*` is supported for controlled administrative integrations, but should not be used for ordinary AI agents.

## Dangerous operations

The following are disabled by default:

- resource deletion;
- system setting writes;
- publication without an approval identifier.

A valid scope alone does not override a disabled policy.

## IP allowlist

An empty allowlist means no IP restriction. When configured, both exact IPv4/IPv6 addresses and CIDR ranges are supported.

For internet-facing deployments, configure an allowlist or place the Bridge behind an authenticated reverse proxy/VPN boundary.

## Rate limiting

Rate limits are stored in `aibridge_rate_limits`. The MySQL/MariaDB increment uses an atomic `INSERT ... ON DUPLICATE KEY UPDATE` operation, avoiding the non-atomic read-modify-write pattern of a process-local counter.

## Idempotency

State-changing requests should provide an `Idempotency-Key`. The key is bound to the principal and operation and stores a SHA-256 request hash. Reusing a key with a different payload is a conflict.

## Audit

Audit events are persisted in `aibridge_audit`. Secrets are recursively redacted before persistence. Audit records should contain request ID, actor, operation, target resource and decision context, but never plaintext credentials.

## Security decision

The `SecurityDecisionPipeline` is the single authorization boundary for state-changing operations. Processors must not bypass it.
