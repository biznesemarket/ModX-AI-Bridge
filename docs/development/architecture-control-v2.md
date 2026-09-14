# Architecture Implementation Control v2

Status: Review

This document updates the previous conceptual architecture control after the PHP/Extra skeleton was introduced.

## Result

The architecture is now represented by actual source boundaries rather than only planned directories.

| Architecture area | Implementation boundary | Status |
|---|---|---|
| Application | `src/Application/` | Present |
| Controllers | `src/Controllers/` | Present |
| REST boundary | `controllers/rest.php` | Stub |
| MCP | `src/MCP/` | Stub |
| Services | `src/Services/` | Present |
| Inspectors | `src/Inspectors/` | Present |
| Contracts | `src/Contracts/` | Present |
| Validators | `src/Validators/` | Present (`ContentValidator`; `RequestValidator` stub removed in Iteration 65) |
| Processors | `src/Processors/` | Present (rollback / job-status / change-verification; resource stubs removed in Iteration 65) |
| Security | `src/Security/` | Present |
| Middleware | — | Removed in Iteration 65 (dead stubs; auth, authorization and rate limiting live in `src/Security/` and the REST boundary) |
| Queue | `src/Queue/` | Stub |
| Audit | `src/Audit/` | Stub |
| Models | `src/Model/` | Present |
| Schema | `schema/aibridge.mysql.schema.xml` | Present |
| Manager | `manager/`, `assets/components/aibridge/` | Shell |
| Transport Package | `_build/` | Scaffold |
| Tests | `tests/` | Initial |

## Critical verification

The previous checkpoint contained unresolved questions about:

- REST routing
- MCP transport
- `src/Controllers/` versus top-level MODX `controllers/`
- migration execution
- queue backend
- snapshot storage
- policy storage
- Manager UI

This iteration resolves the structural distinction:

- `core/components/aibridge/controllers/` is the MODX-facing controller boundary.
- `core/components/aibridge/src/Controllers/` contains application-level controller adapters.
- Business logic remains in `src/Application/`, `src/Services/`, validators and policy services.

The remaining unresolved runtime concerns are deliberately deferred.

## Important MODX 3 decision

The Extra targets MODX 3.2+ and PHP 8.1+.

The xPDO package is namespaced and loaded through `addPackage()` using the PSR-4 layout.

The implementation must not fall back to MODX 2.x global model class names.

## Next verification gate

Before Iteration 4.1 can be marked Stable:

```text
[ ] composer install
[ ] PHP syntax check
[ ] PHPUnit passes without MODX
[ ] install Extra into disposable MODX 3.2+
[ ] bootstrap loads
[ ] xPDO schema parses
[ ] generated metadata/maps load
[ ] database tables install
[ ] Manager page opens
[ ] MODX processor executes
[ ] Transport Package builds
[ ] Transport Package installs
```
