# MCP Security Model

MCP does not bypass REST security. A tool invocation is converted into a security request and evaluated by the same `SecurityDecisionPipeline` used by application operations.

```text
MCP request
  -> authenticated principal
  -> tool/resource mapping
  -> SecurityDecisionPipeline
      -> IP allowlist
      -> scope authorization
      -> operation policy
      -> approval gate
  -> application service
  -> audit/idempotency layers where required
```

The MCP server never receives or returns the bearer secret after authentication. Tool failures intentionally avoid returning exception messages that may disclose internals.
