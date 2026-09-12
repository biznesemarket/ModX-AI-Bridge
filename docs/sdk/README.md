# SDK & Developer Experience

ModX AI Bridge provides official PHP and TypeScript SDKs. Both clients are thin adapters over the versioned REST API and reuse the same authentication, profile, Content Contract, execution, job and idempotency semantics.

## Design rules

- SDKs never bypass REST/MCP security.
- Mutation methods require an `Idempotency-Key`.
- SDK errors preserve HTTP status, machine-readable code and safe details.
- Job polling is explicit; SDKs do not hide long-running execution behind arbitrary HTTP timeouts.
- MCP is an adapter over the same Bridge endpoint and application capabilities.

## Packages

- `sdk/php` — PHP 8.1+.
- `sdk/typescript` — TypeScript/ES2022.

See `docs/api/openapi.yaml` and `docs/api/contracts.md` for the public contract.
