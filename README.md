# ModX AI Bridge — Iteration 21 (Stabilization)

## Observability, Admin UI & Operations Console

Iteration 17 added an operational console inside MODX Manager. It provides visibility into Bridge profiles, token metadata, policies, jobs, audit events, fingerprints and runtime readiness.

The console is not a replacement for the external REST/MCP security pipeline. Manager access is separately protected by MODX Manager authentication and the `aibridge_manage` permission.

Status: **Stabilization in progress — target `0.1.0-rc1`**

## Iteration 19 — Approval Workflow
Durable change requests, approvals and controlled execution are provided by `AIBridge\Workflow`.

## Iteration 20 — Verification, Rollback & Release Governance
Post-execution verification, controlled rollback and the fail-closed `stable-gate.sh` certification pipeline are provided by `AIBridge\Verification`.

## Final Integration & Stabilization

The project is not marked Stable until `scripts/final-stabilization.sh` completes successfully in an environment with Docker, Docker Compose, MODX 3.2.x and MySQL. The current source tree is undergoing the stabilization iterations (21+) defined in the iteration implementation plan; it has not received runtime certification yet.

## AI Agent Development

This repository contains a dedicated operational contract for AI coding agents.

- `AGENTS.md` — mandatory agent rules and architectural invariants.
- `docs/ai-agent/README.md` — detailed engineering workflow.
- `docs/ai-agent/ITERATION-PLAN.md` — stabilization and certification iterations.
- `docs/ai-agent/TROUBLESHOOTING.md` — runtime/security troubleshooting.
- `docs/ai-agent/TASK-TEMPLATE.md` — task specification template.
- `docs/ai-agent/AGENT-CHECKLIST.md` — daily and release checklist.
