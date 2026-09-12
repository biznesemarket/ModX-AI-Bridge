# Settings Reference

Status: Draft

This document is the normative reference for the initial Bridge configuration surface.

## Namespace

All package settings use the `aibridge.` prefix.

## Boolean safety switches

The following switches are deny-by-default controls:

- `aibridge.rest_enabled`
- `aibridge.mcp_enabled`
- `aibridge.queue_enabled`
- `aibridge.allow_resource_delete`
- `aibridge.allow_setting_write`
- `aibridge.approval_required_for_publish`
- `aibridge.require_https`

## Non-secret numeric controls

- `aibridge.token_expiry_days`
- `aibridge.rate_limit_per_minute`
- `aibridge.max_request_body_bytes`

These values require validation before being consumed by security middleware.

## Forbidden configuration practice

Do not put bearer tokens, API keys, database passwords or third-party credentials into `config.php`, source control, Transport Package manifests or documentation examples.
