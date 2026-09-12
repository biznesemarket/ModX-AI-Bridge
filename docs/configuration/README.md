# Configuration

Status: Draft

Configuration is divided into three layers:

1. **Package defaults** — versioned in `core/components/aibridge/config/config.php`.
2. **MODX system settings** — runtime overrides using the `aibridge.*` key namespace.
3. **Operational policy** — security-sensitive rules that must be explicit and auditable.

## Core settings

| Key | Default | Purpose |
|---|---:|---|
| `aibridge.environment` | `development` | Runtime environment label. |
| `aibridge.rest_enabled` | `false` | Enables the REST API boundary. |
| `aibridge.mcp_enabled` | `false` | Enables MCP integration. |
| `aibridge.audit_enabled` | `true` | Enables audit recording. |
| `aibridge.queue_enabled` | `false` | Enables asynchronous jobs. |
| `aibridge.require_https` | `true` | Rejects insecure external API transport. |
| `aibridge.token_expiry_days` | `90` | Default token lifetime. |
| `aibridge.rate_limit_per_minute` | `60` | Default request budget per token. |
| `aibridge.max_request_body_bytes` | `1048576` | Maximum accepted request body. |
| `aibridge.approval_required_for_publish` | `true` | Requires approval before publication. |
| `aibridge.allow_resource_delete` | `false` | Destructive resource operations are disabled by default. |
| `aibridge.allow_setting_write` | `false` | MODX system-setting writes are disabled by default. |

## Security baseline

Production should retain HTTPS enforcement, audit logging and approval for publication. Resource deletion and system-setting writes should remain disabled unless a documented integration requires them.

Secrets and bearer tokens must not be stored in repository configuration files. The audit layer must redact authorization headers, tokens, passwords, API keys and other credentials.

## Configuration precedence

Runtime values are resolved as:

`package default → MODX system setting → explicit operation policy`

Operation policy has authority over a permissive package default. A request must never gain additional privileges merely because a global setting is permissive.
