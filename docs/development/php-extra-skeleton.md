# PHP / Extra Skeleton

Status: Review

## Purpose

This document defines the concrete implementation skeleton introduced by Iteration 4.1.

The skeleton targets current MODX 3.x and follows the MODX 3/xPDO 3 namespaced model approach.

## Namespace

The project namespace is:

```text
AIBridge\
```

The xPDO model namespace is:

```text
AIBridge\Model
```

PSR-4 root:

```text
core/components/aibridge/src/
```

## Runtime layers

```text
MODX Manager / REST / MCP
          ↓
Boundary Controllers / Processors
          ↓
Application Services
          ↓
Contracts / Validators / Policies
          ↓
Inspectors / Infrastructure
          ↓
xPDO / MODX Core / Database
```

## Rules

1. Controllers and processors are adapters, not domain services.
2. REST and MCP must call the same application services.
3. Authentication precedes authorization.
4. Authorization precedes validation/execution.
5. Validation must be explicit before mutation.
6. Mutations must become auditable.
7. Preview must not imply publish.
8. Tokens are represented by hashes, never stored in plaintext.
9. Secrets must be redacted before audit logging.
10. xPDO classes must use MODX 3 namespaced conventions.
11. Installation logic belongs to Transport Package builders/resolvers.
12. Runtime logic must not depend on a specific Manager page.

## Model strategy

The source schema is:

```text
core/components/aibridge/schema/aibridge.mysql.schema.xml
```

The schema uses xPDO 3 metadata conventions and the `AIBridge\Model` namespace.

The model classes are placed under:

```text
core/components/aibridge/src/Model/
```

The package is registered from `bootstrap.php` with:

```php
$modx->addPackage(
    'AIBridge\\Model',
    $namespacePath . 'src/',
    null,
    'AIBridge\\'
);
```

## Current limitations

The skeleton does not claim that the following are implemented:

- production REST routing
- token issuance/revocation
- scopes
- rate limiting
- policy evaluation
- resource mutation
- Site Contract generation
- fingerprint algorithm
- queue persistence
- snapshots/rollback
- MCP transport
- generated xPDO maps
- production Transport Package vehicles/resolvers

Those items are intentionally represented by boundaries/stubs.

## Acceptance criteria for this iteration

- [x] namespace exists
- [x] PSR-4 mapping exists
- [x] bootstrap exists
- [x] xPDO 3 schema exists
- [x] model classes exist
- [x] service boundaries exist
- [x] processor boundaries exist
- [x] security boundaries exist
- [x] Manager shell exists
- [x] build scaffold exists
- [x] PHPUnit configuration exists
- [ ] real MODX installation test
- [ ] generated xPDO metadata/maps verified by xPDO tooling
- [ ] real Transport Package build verified
