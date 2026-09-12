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

## Transport

The HTTP boundary is implemented by `AIBridge\Api\RestApi` behind the front controller
`assets/components/aibridge/api/index.php`, mapped to `/api/ai/v2/*` by the web server
(`docker/modx/apache/aibridge-api.conf`, `deploy/nginx/aibridge-api.conf`).

Security order on every request: authentication (Bearer token, hashed lookup) → rate limit →
IP allowlist → authorization/scopes → policy → approval binding → idempotency contract → Application service.

- Mutating endpoints require the `Idempotency-Key` header. The request is queued and answers
  `202 {"success":true,"job_id":N,"status":"queued",...}`; poll `GET /api/ai/v2/jobs/{id}`.
- Idempotency is enforced in the execution layer: replaying the same key and payload returns the stored
  result and does not repeat the mutation.
- Successful reads answer `200 {"success":true,"data":{...},"request_id":"..."}`.
- `GET /api/ai/v2/health` is public; everything else requires a bearer token.
- `POST /api/ai/v2/mcp` is the MCP JSON-RPC endpoint (see `docs/mcp/transport.md`).

Example:

```text
curl -H "Authorization: Bearer <token>" http://host/api/ai/v2/site/schema
curl -X POST -H "Authorization: Bearer <token>" -H "Idempotency-Key: <uuid>" \
     -H "Content-Type: application/json" -d '{"pagetitle":"...","template":1}' \
     http://host/api/ai/v2/resources
```

