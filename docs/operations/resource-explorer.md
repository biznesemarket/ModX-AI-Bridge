# Resource Explorer & Site Operations UI

Status: Review

## Purpose

The Resource Explorer provides a Manager-side operational interface for inspecting and safely initiating resource operations. It is not a second CRUD implementation.

## Capabilities

- resource tree for the root context;
- resource search;
- Template filter;
- TV name/value filter;
- resource detail view;
- Content Contract inspection;
- Content QA inspection;
- safe preview;
- create/update/delete/publish execution jobs;
- fingerprint comparison;
- job progress polling.

## Security boundary

Browser actions call Manager Processors only. Mutation operations are dispatched to `QueueManager`, then executed by `ResourceExecutionJobHandler`, which delegates to `ResourceExecutionService`.

```text
Manager UI
  -> Manager Processor
  -> QueueManager
  -> Worker
  -> ResourceExecutionService
  -> SecurityDecisionPipeline
  -> Policy / Idempotency / QA / Snapshot / Transaction
  -> MODX
```

No browser code directly writes `modResource` or `modTemplateVarResource` records.

## Manager authorization

Every Resource Explorer processor requires the `aibridge_manage` Manager permission. The Manager principal is explicitly marked as `manager_authorized` and is accepted by the existing Security Decision Pipeline only for the documented resource/site/content operations.

## Preview

Preview is executed synchronously and remains non-persistent. It uses the existing `PreviewService` and does not execute snippets.

## Mutations

Update, delete and publish are queued. The UI creates a unique idempotency key for each Manager operation. The worker executes the same Resource Execution Service used by REST/MCP clients.

Delete remains subject to the global `allow_resource_delete` policy. Publish remains subject to the existing approval policy.
