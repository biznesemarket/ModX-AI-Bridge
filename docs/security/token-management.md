# Token Management

Status: **Review**

Bridge tokens are opaque 256-bit random values represented as hexadecimal strings. Only their SHA-256 digest is stored.

Recommended scopes:

| Scope | Purpose |
|---|---|
| `site:read` | Site Schema and Fingerprint discovery |
| `content:validate` | Content QA |
| `resource:preview` | Non-committing resource preview |
| `resource:write` | Resource create/update |
| `resource:delete` | Resource deletion |
| `resource:publish` | Publication |
| `settings:write` | System setting changes |

Issue the minimum scopes required by an integration. Rotate tokens periodically and revoke compromised credentials immediately.
