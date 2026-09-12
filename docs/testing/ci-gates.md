# CI Gates

The CI pipeline is intentionally split into deterministic and runtime jobs.

## Deterministic

- PHP syntax check
- Composer dependency validation
- PHPUnit unit suite
- static contract
- API contract
- MCP contract
- security suite that does not require external services

## Runtime

The Docker job provisions MySQL and MODX 3.2.x, installs the Extra, generates xPDO models, then executes integration and E2E suites.

A runtime job must fail when a required runtime dependency is unavailable. It must not downgrade a missing MODX environment to a successful mock test.

## Release rule

`Stable` requires green deterministic and runtime gates. `Review` is the correct status when runtime execution has not occurred in the current environment.
