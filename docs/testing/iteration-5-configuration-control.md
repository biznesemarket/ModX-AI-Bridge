# Iteration 5 — Installation and Configuration Control

Status: Review

## Scope

Iteration 5 establishes the installation and runtime configuration contract before implementing the external API authentication surface.

## Acceptance criteria

- [x] Package defaults are centralized.
- [x] Runtime configuration can be read through a typed configuration object.
- [x] REST/MCP are disabled by default.
- [x] Resource deletion is disabled by default.
- [x] System-setting writes are disabled by default.
- [x] Publication approval is enabled by default.
- [x] HTTPS is required by default.
- [x] Installation and post-install verification are documented.
- [ ] Actual MODX runtime execution in Docker.
- [ ] Manager configuration UI.
- [ ] Token provisioning UI/API.

## Decision

The next implementation stage should not expose a production API until token authentication, scope authorization, rate limiting and audit persistence are implemented and tested together.
