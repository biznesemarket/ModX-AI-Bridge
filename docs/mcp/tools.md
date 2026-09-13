# MCP Tools Reference

## Protocol methods

The transport adapter forwards JSON-RPC requests to `McpServer::handle()`:

- `initialize`
- `tools/list`
- `tools/call`
- `resources/list`
- `resources/read`
- `resources/templates/list`
- `prompts/list`
- `prompts/get`

Unknown methods return JSON-RPC error `-32601`; unknown tools return `-32602`; denied operations return `-32003`.

## site_schema

Returns the discovered MODX site schema. Optional arguments: `limit`, `root_id`, `context_key`, `template_id`
(the resource list inside the schema is filtered and limited accordingly).

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

## resource_read

Reads a persisted MODX resource by numeric `id` and returns a whitelisted projection (`pagetitle`, `alias`,
`template`, `published`, `content`, timestamps, ...). Template variables bound to the resource template are
included read-only under the `tvs` map (TV name → string value; structured values are JSON-encoded). A missing
or soft-deleted resource returns `resource_not_found` in the tool result payload (not a JSON-RPC error).

Required scope: `resource:read`. The same operation backs `GET /api/ai/v2/resources/{id}`.

## resource_list

Lists persisted, non-deleted MODX resources with optional filters: `parent`, `template`, `context_key`, `published`,
`tv_name`/`tv_value` (match an explicit template-variable value), `q` (pagetitle/alias/description match),
`limit` (1..100, default 25), `offset`, `sort`, `dir`. Returns a paginated
summary projection (id/parent/pagetitle/alias/template/context/timestamps/state, without `content` or TVs) plus
`count`, `total`, `limit` and `offset`. Invalid filter values return `invalid_filter` in the tool result payload.

Required scope: `resource:read`. The same operation backs `GET /api/ai/v2/resources`.

## Resource templates

`resources/templates/list` advertises the URI templates the server serves. `modx://resource/{id}` resolves to
the read-back projection of one persisted resource — the same payload as the `resource_read` tool and
`GET /api/ai/v2/resources/{id}`. A missing or soft-deleted resource returns JSON-RPC error `-32002`
(`Resource not found` with `reason: resource_not_found`).

Required scope: `resource:read`.
