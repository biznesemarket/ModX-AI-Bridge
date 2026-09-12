# Installation

Status: Draft

ModX AI Bridge is distributed as a MODX Transport Package. The supported installation target is MODX Revolution 3.2+ with PHP 8.1+.

## Installation sequence

1. Verify the MODX and PHP requirements.
2. Create a database backup or filesystem/database snapshot appropriate to the deployment.
3. Install the `modx-ai-bridge-*.transport.zip` package through MODX Manager or the MODX package API.
4. Confirm that the `aibridge` namespace is registered.
5. Confirm that the **AI Bridge** Manager menu is present.
6. Configure Bridge settings before issuing any integration token.
7. Keep REST and MCP disabled until authentication, authorization and policy configuration have been reviewed.

## Upgrade rule

Do not overwrite an existing Bridge installation with source files manually. Upgrades must use a versioned Transport Package and its resolvers/migrations.

## Uninstall rule

Uninstallation must be treated separately from upgrade. Database tables containing audit, jobs, schemas, fingerprints or snapshots should not be deleted implicitly unless the operator explicitly selects destructive cleanup.

## Development installation

The repository includes a Docker-based MODX integration environment. Use `make test-modx` for the complete CI-oriented installation and verification sequence.
