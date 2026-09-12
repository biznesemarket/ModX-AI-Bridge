# MCP Tools Reference

## Protocol methods

The transport adapter forwards JSON-RPC requests to `McpServer::handle()`:

- `initialize`
- `tools/list`
- `tools/call`
- `resources/list`
- `resources/read`
- `prompts/list`
- `prompts/get`

Unknown methods return JSON-RPC error `-32601`; unknown tools return `-32602`; denied operations return `-32003`.

## site_schema

Returns the discovered MODX site schema. Optional arguments: `limit`, `root_id`.

Required scope: `site:read`.

## site_fingerprint

Returns the deterministic fingerprint derived from the normalized site schema.

Required scope: `site:read`.

## content_contract

Builds the AI content contract for an optional template identifier.

Required scope: `site:read`.

## content_validate

Validates proposed content against a Content Contract and returns errors/warnings.

Required scope: `content:validate`.
