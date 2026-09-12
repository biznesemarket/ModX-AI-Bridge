# MCP Transport Boundary

`McpServer` is deliberately transport-neutral. The HTTP adapter lives in the REST front controller
(`AIBridge\Api\RestApi::handle` for `POST /api/ai/v2/mcp`) and never contains business logic.

The adapter:

1. enforces HTTPS according to `aibridge_require_https`;
2. authenticates the bearer token before dispatch (`TokenAuthenticator`, SHA-256 token lookup);
3. applies the fixed-window rate limit;
4. constructs the principal (including `profile_id`) and client IP context;
5. validates the JSON-RPC envelope (`jsonrpc: "2.0"`);
6. passes the message to `McpServer::handle()`;
7. preserves request IDs and never logs bearer tokens.

`McpServer::handle()` applies the full security pipeline per tool/resource (`-32003` on denial) and
supports: `initialize`, `tools/list`, `tools/call`, `resources/list`, `resources/read`, `prompts/list`,
`prompts/get`; unknown methods return `-32601`, unknown tools `-32602`, unknown resources `-32002`.

`resource_publish` requires `approval_id` and `change_id`; the approval record is verified against the
change before execution. Write/delete/publish tools run through `Application` → `ResourceExecutionService`
with the same security boundary as REST.

Streamable HTTP/SSE support belongs to this adapter layer, not to MCP tools themselves.
