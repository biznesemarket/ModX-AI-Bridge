# Real MODX Integration Test

Status: Review

This is the Iteration 4.2 integration gate. It is intentionally executed against a real MODX 3.2+ installation rather than mocks.

## Supported baseline

- MODX 3.2+
- PHP 8.1+
- PHP 8.2+ recommended
- MySQL 5.7+; MySQL 8+ or MariaDB 10.6+ recommended

Current MODX documentation confirms that MODX 3.2 raised the minimum PHP version to 8.1 and recommends 8.2+. xPDO 3 is loaded through Composer and custom Extra models use namespaced PSR-4 classes.

## 1. Prepare MODX

For a disposable development installation:

```bash
composer create-project modx/revolution modx 3.x-dev
```

Install MODX using the normal setup or CLI configuration.

## 2. Prepare the Extra

From the Extra repository:

```bash
composer install
composer build-schema
composer verify-generated-model
```

The schema parser must produce:

```text
core/components/aibridge/src/Model/*.php
core/components/aibridge/src/Model/metadata.mysql.php
core/components/aibridge/src/Model/mysql/*.php
```

Do not manually edit generated model metadata or maps.

## 3. Build the Transport Package

Point the builder at the real MODX installation:

```bash
MODX_ROOT=/absolute/path/to/modx php _build/build.php
```

The expected artifact is:

```text
core/packages/aibridge-0.1.0-alpha2.transport.zip
```

The build uses the MODX 3 namespaced `modPackageBuilder` and `xPDOTransport` classes.

## 4. Install the package

```bash
MODX_ROOT=/absolute/path/to/modx \
php scripts/install-package.php aibridge-0.1.0-alpha2
```

The installer performs:

1. `workspace/packages/scanlocal`
2. `workspace/packages/install`
3. Transport Package installation
4. namespace registration
5. Manager menu installation
6. system settings installation
7. component file installation
8. xPDO model table creation

## 5. Run integration tests

```bash
MODX_ROOT=/absolute/path/to/modx vendor/bin/phpunit tests/Integration
```

The integration suite checks:

- MODX version
- Extra namespace
- bootstrap execution
- xPDO model class loading
- xPDO table registration
- object creation
- MODX service container registration
- Manager menu registration
- custom processor execution

## 6. Manager verification

Log into the MODX Manager and verify:

```text
Extras / Components
    └── AI Bridge
```

Opening the menu must load the `home` Manager controller and display the AI Bridge shell.

The controller must extend the MODX 3 namespaced `MODX\Revolution\modExtraManagerController`.

## 7. Processor verification

The Extra includes a deliberately minimal health processor. It is executed using MODX 3's custom processor loading mechanism:

```php
$response = $modx->runProcessor(
    'health',
    [],
    ['processors_path' => MODX_CORE_PATH . 'components/aibridge/processors/']
);
```

The processor returns:

```json
{
  "component": "modx-ai-bridge",
  "status": "ok",
  "version": "0.1.0"
}
```

## 8. Service container verification

The bootstrap registers:

```text
AIBridge\Application\Application
```

under:

```text
aibridge.application
```

The test verifies that the service is available through `$modx->services`.

MODX 3's Dependency Injection Container is Pimple-based. New custom services should be registered with `add()` and loaded with `get()` rather than the deprecated `getService()` path.

## 9. Pass criteria

Iteration 4.2 is **PASS** only when all of the following are true:

```text
[ ] PHP preflight passes
[ ] Composer dependencies install
[ ] xPDO schema parser succeeds
[ ] generated model classes exist
[ ] generated metadata exists
[ ] generated MySQL maps exist
[ ] MODX 3.2+ starts
[ ] Extra Transport Package builds
[ ] package is discovered by scanlocal
[ ] package installs successfully
[ ] namespace exists
[ ] database tables exist
[ ] bootstrap executes
[ ] xPDO model loads
[ ] service container entry exists
[ ] Manager menu exists
[ ] Manager controller opens
[ ] custom processor executes
[ ] PHPUnit integration suite passes
```

A source-level inspection or PHP syntax check is not sufficient to mark this gate PASS.
