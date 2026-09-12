# MCP Tools Reference

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
