# Iteration 15 — Testing & Quality Engineering

**Status:** Review  
**Scope:** verification of the accumulated AI Bridge runtime, API, MCP, security, queue, multi-site and execution layers.

## Objectives

Iteration 15 adds a repeatable quality gate. It does not introduce new product capabilities.

The quality strategy is layered:

1. static analysis and PHP syntax;
2. unit tests for deterministic domain logic;
3. API and MCP contract tests;
4. security tests;
5. integration tests against real MODX/xPDO;
6. multi-site isolation tests;
7. queue concurrency and idempotency tests;
8. execution rollback tests;
9. end-to-end Docker tests.

## Required quality gates

A release candidate must pass all deterministic gates and must not report a skipped required gate as successful.

| Gate | Environment | Required |
|---|---|---:|
| PHP lint | PHP 8.1+ | yes |
| Unit | PHP + PHPUnit | yes |
| Static contract | PHP | yes |
| API contract | PHP | yes |
| MCP contract | PHP | yes |
| Security suite | PHP + DB where required | yes |
| MODX integration | MODX 3.2.x + MySQL | yes |
| Queue concurrency | MySQL transaction/locking | yes |
| Multi-site isolation | 2 profiles/sites | yes |
| E2E | Docker + real MODX | yes |

## Test principles

- Tests must assert observable behavior, not implementation details.
- Security tests are deny-by-default: an unexpected allow is a failure.
- Queue tests must prove that one job cannot be claimed by two workers concurrently.
- Idempotency tests must prove that the same key does not execute a mutation twice.
- Rollback tests must prove that a failed mutation does not leave a partial resource state.
- Multi-site tests must prove that profile context cannot cross boundaries.
- Contract tests must validate response shape and error codes independently from transport implementation.
- Docker E2E is the final gate for claims about real MODX runtime behavior.
