# Iteration 4.2 — Real MODX Integration & Transport Package Control

Status: Review

## Scope

Iteration 4.2 does not introduce a new application architecture. It verifies that the Iteration 4.1 boundaries conform to a real MODX 3.2+ runtime.

## MODX 3 decisions verified against current documentation

### PHP baseline

The target baseline is PHP 8.1+. PHP 8.2+ is recommended by current MODX 3.x documentation.

### xPDO 3

The Extra uses:

```text
core/components/aibridge/schema/aibridge.mysql.schema.xml
core/components/aibridge/src/Model/
```

with:

```xml
<model package="AIBridge\Model"
       baseClass="xPDO\Om\xPDOObject"
       platform="mysql"
       defaultEngine="InnoDB"
       version="3.0">
```

The model is loaded through:

```php
$modx->addPackage(
    'AIBridge\\Model',
    $namespacePath . 'src/',
    null,
    'AIBridge\\'
);
```

This follows the MODX 3/xPDO 3 PSR-4 Extra model layout.

### Dependency Injection

The Extra registers its application service through `$modx->services->add()` in `bootstrap.php`.

The deprecated `getService()` path is deliberately not used.

### Manager

The Manager menu uses the MODX 3 `modMenu` action/namespace model. No `modAction` is used.

The CMP controller extends:

```php
MODX\Revolution\modExtraManagerController
```

### Processors

The Extra uses a class-based processor and MODX 3's `processors_path` loading mechanism. No flat-file processor is used.

### Transport Package

The build uses:

```php
MODX\Revolution\Transport\modPackageBuilder
xPDO\Transport\xPDOTransport
```

The generated package follows the MODX transport signature:

```text
aibridge-0.1.0-alpha2.transport.zip
```

## Verification matrix

| Gate | Verification | Result in this artifact |
|---|---|---|
| PHP | PHP >= 8.1 preflight | Implemented |
| Extensions | MODX required extensions check | Implemented |
| Composer | `composer install` | CI configured |
| xPDO | `composer build-schema` | Implemented |
| Model | generated classes | CI-generated |
| Metadata | xPDO 3 metadata | CI-generated + verified |
| Maps | MySQL maps | CI-generated + verified |
| MODX | 3.2+ runtime | CI harness configured |
| Bootstrap | Extra bootstrap | Integration test |
| Namespace | `aibridge` | Transport Package + test |
| Database | table creation | Install resolver + test |
| Service container | `aibridge.application` | Integration test |
| Manager | menu/controller | Integration test + manual gate |
| Processor | health processor | Integration test |
| Transport Package | build | CI build step |
| Installation | package install processor | CI install step |
| PHPUnit | unit + integration | CI test step |

## What can be claimed now

The source tree is structurally prepared for real MODX 3.2+ verification and contains an executable integration harness.

## What cannot be claimed without a MODX runtime

The repository cannot truthfully mark the integration gate as PASS until a real MODX 3.2+ installation has completed the build, package installation and PHPUnit integration suite.

A PHP syntax check alone is not evidence of MODX runtime compatibility.
