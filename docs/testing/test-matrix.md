# Test Matrix

| Area | Scenario | Expected invariant |
|---|---|---|
| Auth | invalid token | 401, no operation |
| Auth | expired token | 401, no operation |
| Scope | missing scope | 403, no operation |
| IP | disallowed CIDR | 403, no operation |
| Rate limit | threshold exceeded | 429, no operation |
| Idempotency | same key twice | one mutation |
| Policy | destructive operation disabled | denied |
| Approval | publish without approval | denied |
| Contract | invalid content | validation failure |
| Snapshot | update/delete | snapshot exists before mutation |
| Transaction | injected failure | rollback |
| Cache | successful mutation | invalidation recorded |
| Audit | denied/allowed operation | audit event exists without secrets |
| Queue | two workers, one job | one winner |
| Queue | stale lease | job requeued |
| Queue | retryable error | retry until max attempts |
| Queue | non-retryable error | terminal failure |
| Profile | token A + profile B | denied |
| Profile | job A + profile B | denied |
| API | malformed JSON | structured 4xx |
| MCP | unknown tool | protocol error |
| MCP | tool without scope | denied |
| MCP | resource read | contract-compliant resource |
| E2E | create/update/preview | real MODX state |
