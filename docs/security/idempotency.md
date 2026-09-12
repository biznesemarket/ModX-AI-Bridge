# Idempotency

Status: **Review**

Idempotency protects state-changing HTTP operations against client retries and network ambiguity.

A request is identified by:

`principal + operation + Idempotency-Key`

The persisted request hash must match on every retry. A different hash with the same identity is rejected as `idempotency_key_reused`.
