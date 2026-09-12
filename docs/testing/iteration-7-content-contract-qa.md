# Iteration 7 — Content Contract & Content QA

Status: **Review**

## Implemented

- ResourceContentContract
- ContentContractService
- ContentQAService
- ContentValidator integration
- Application/API boundaries for content contract and validation
- Unit tests for contract construction and QA

## Deliberate limitations

This iteration does not:

- save content;
- publish content;
- sanitize HTML;
- execute snippets;
- infer mandatory TVs from naming conventions;
- claim semantic validity of JSON-LD beyond JSON syntax;
- perform browser rendering.

Those capabilities belong to subsequent execution, security, and preview iterations.

## Acceptance criteria

- Content contract is deterministic for identical schema input.
- Required-field validation is blocking.
- HTML policy violations are blocking.
- SEO range deviations are warnings by default.
- JSON-LD syntax errors are blocking when JSON-LD validation is enabled.
- QA remains independent of MODX persistence.
