# MCP Transport Boundary

`McpServer` is deliberately transport-neutral. The project does not hard-code a particular web server transport into the domain layer.

A future transport adapter must:

1. accept only HTTPS;
2. authenticate the bearer token before dispatch;
3. construct the principal and client IP context;
4. pass the JSON-RPC message to `McpServer::handle()`;
5. preserve request IDs;
6. apply response headers and body-size limits;
7. never log bearer tokens.

Streamable HTTP/SSE support belongs to this adapter layer, not to MCP tools themselves.
