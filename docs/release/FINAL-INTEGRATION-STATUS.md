# Final Integration & Stabilization — Status

Status: **BLOCKED — RUNTIME REQUIRED**

This release has passed static validation available in the current environment, but it is **not certified Stable** because Docker is unavailable in the execution environment.

## Executed gates

- PHP syntax validation: PASS
- Shell syntax validation: PASS
- Transport archive integrity: PASS
- Source/package static inspection: PASS

## Required runtime gates

The following must be executed against a real MODX 3.2.x installation and MySQL 8.x:

1. Fresh MODX installation.
2. Extra Transport Package build and installation.
3. xPDO schema/model generation and migration execution.
4. MODX Manager bootstrap and namespace verification.
5. REST authentication, authorization and API contract tests.
6. MCP initialize/tools/resources/prompts contract tests.
7. Resource create/update/delete/preview/publish E2E.
8. Concurrent queue claim test proving one job is claimed once.
9. Idempotency replay test.
10. Approval → execution → verification test.
11. Verification mismatch → rollback test.
12. Multi-site profile isolation test.
13. Health/readiness test.
14. Package upgrade and rollback test.
15. Secret redaction and security regression tests.

## Stable certification rule

`Stable` MUST NOT be assigned until every required runtime gate passes. A missing Docker/runtime is a failure of certification, not a pass.
