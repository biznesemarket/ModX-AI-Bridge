# Settings Reference

Status: Normative

This document is the normative reference for the Bridge configuration surface.

## Namespace and key format

Package settings are MODX system settings with the `aibridge_` prefix (underscore, e.g.
`aibridge_rest_enabled`). `ConfigFactory::fromModx()` reads defaults from
`core/components/aibridge/config/config.php` and overrides them with these settings.
Array-typed settings (`aibridge_ip_allowlist`, `aibridge_blocked_operations`) are stored as JSON strings
and decoded by `ConfigFactory`.

## Boolean safety switches

The following switches are deny-by-default controls:

- `aibridge_rest_enabled` (default `0`)
- `aibridge_mcp_enabled` (default `0`)
- `aibridge_queue_enabled` (default `0`)
- `aibridge_allow_resource_delete` (default `0`)
- `aibridge_allow_setting_write` (default `0`)
- `aibridge_approval_required_for_publish` (default `1`)
- `aibridge_require_https` (default `1`)
- `aibridge_security_fail_closed` (default `1`)
- `aibridge_audit_enabled` (default `1`)

## Network and identity controls

- `aibridge_ip_allowlist` — JSON array of exact IPs or CIDR ranges, e.g. `["10.0.0.0/24","127.0.0.1"]`.
  **Fail closed:** an empty list denies every non-manager request. Configure it explicitly before
  enabling REST/MCP.
- `aibridge_blocked_operations` — JSON array of operations that are never allowed, default
  `["settings.write"]`.
- `aibridge_token_expiry_days` (default `90`)
- `aibridge_rate_limit_per_minute` (default `60`)

## Read-back controls

- `aibridge_redacted_tvs` — JSON array of template-variable names whose values are omitted from the
  read-back projection (`GET /api/ai/v2/resources/{id}`, MCP `resource_read`, `modx://resource/{id}`).
  Default `[]`. Use it when a TV stores a secret; the rest of the `tvs` map keeps its normal shape.

## Non-secret numeric controls

- `aibridge_max_request_body_bytes` (default `1048576`)
- `aibridge_idempotency_ttl_seconds` (default `86400`)

## Environment metadata

- `aibridge_environment` (default `production`)
- `aibridge_api_version` (default `v2`)
- `aibridge_core_path`, `aibridge_assets_url`

These values require validation before being consumed by security middleware.

## Forbidden configuration practice

Do not put bearer tokens, API keys, database passwords or third-party credentials into `config.php`, source control, Transport Package manifests or documentation examples.
