# MCP & AI Integration

Status: **Review**

Iteration 10 adds a transport-neutral MCP server over the existing Application and Security layers.

## Design principles

1. MCP is an adapter, not a second business layer.
2. Tools map to Application Services.
3. MCP resources are read-only projections unless an explicit execution tool exists.
4. Every executable tool passes through `SecurityDecisionPipeline`.
5. Dangerous MODX operations remain disabled or approval-gated by policy.
6. MCP and REST expose the same domain contracts.

## Supported MCP surface

- `initialize`
- `tools/list`
- `tools/call`
- `resources/list`
- `resources/read`
- `resources/templates/list`
- `prompts/list`
- `prompts/get`

Resource URI templates (for example `modx://resource/{id}`) are advertised through `resources/templates/list`
and resolved through `resources/read` under the same security pipeline as the matching tool.

The implementation is transport-neutral. An HTTP/SSE or Streamable HTTP adapter can invoke `McpServer::handle()` without duplicating business logic.

## Current tools

| Tool | Security operation | Purpose |
|---|---|---|
| `site_schema` | `site.schema` | Discover site structure |
| `site_fingerprint` | `site.fingerprint` | Return deterministic fingerprint |
| `content_contract` | `site.schema` | Build content contract |
| `content_validate` | `content.validate` | Validate AI-generated content |

Write/delete/publish tools are deliberately not exposed in this iteration.
