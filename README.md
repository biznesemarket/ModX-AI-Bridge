# ModX AI Bridge — Iteration 21 (Stabilization)

## Observability, Admin UI & Operations Console

Iteration 17 added an operational console inside MODX Manager. It provides visibility into Bridge profiles, token metadata, policies, jobs, audit events, fingerprints and runtime readiness.

The console is not a replacement for the external REST/MCP security pipeline. Manager access is separately protected by MODX Manager authentication and the `aibridge_manage` permission.

Status: **Stable `0.9.0`** (tag `v0.9.0`; certified `aibridge-0.9.0.transport.zip`)

## Iteration 19 — Approval Workflow
Durable change requests, approvals and controlled execution are provided by `AIBridge\Workflow`.

## Iteration 20 — Verification, Rollback & Release Governance
Post-execution verification, controlled rollback and the fail-closed `stable-gate.sh` certification pipeline are provided by `AIBridge\Verification`.

## Final Integration & Stabilization

`Stable` is only declared when `AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh` completes green
in an environment with Docker, Docker Compose, MODX 3.2.x, MySQL, PHP/Composer and Node.js. `0.1.0` was
certified on commit `160a659` (run `34716441523`); `0.1.1` fixed system-settings packaging; `0.1.2` added
supply-chain pinning; `0.1.3` added the TypeScript SDK live HTTP E2E; `0.2.0` added the resource read-back API,
`getResource()` in both SDKs, `resource_read` MCP tool and the `aibridge_version` setting; `0.3.0` added
template variables to the read-back projection (`tvs`); `0.4.0` added the filtered, paginated resource list;
`0.5.0` added the MCP resource template `modx://resource/{id}`; `0.6.0` added the `tv_name`/`tv_value` list
filter; `0.7.0` added contexts to `/capabilities` and made the TV filter join-based; `0.7.1` fixed the manager
explorer/profiles query handling; `0.8.0` added `context_key`/`template_id` filters (with a real limit) to the
site-schema resource list plus manager-surface integration coverage; `0.9.0` is the current Stable minor
release and adds a recursive `parent` filter (`depth`, 1..10) and `parent` sorting to the resource list.
Evidence:
`docs/release/0.9.0.md`, `docs/release/stable-status.md`, `docs/testing/STATUS.md`.

## AI Agent Development

This repository contains a dedicated operational contract for AI coding agents.

- `AGENTS.md` — mandatory agent rules and architectural invariants.
- `docs/ai-agent/README.md` — detailed engineering workflow.
- `docs/ai-agent/ITERATION-PLAN.md` — stabilization and certification iterations.
- `docs/ai-agent/TROUBLESHOOTING.md` — runtime/security troubleshooting.
- `docs/ai-agent/TASK-TEMPLATE.md` — task specification template.
- `docs/ai-agent/AGENT-CHECKLIST.md` — daily and release checklist.
