# AI-oriented API Contracts

Contract version: `1.0`.

Every SDK method maps to a versioned Bridge operation. The SDK is not the source of truth; OpenAPI and server-side processors remain authoritative.

Mutation invariants:

1. Authentication and authorization occur server-side.
2. `Idempotency-Key` is mandatory for create/update/delete/publish.
3. Content validation precedes persistence where applicable.
4. The server may return a job reference for asynchronous work.
5. Clients must treat unknown fields as forward-compatible and must not infer privileged capabilities from them.

Error envelope:

```json
{"error":{"code":"policy_denied","message":"Operation denied","details":{"operation":"resource.delete"},"request_id":"..."}}
```
