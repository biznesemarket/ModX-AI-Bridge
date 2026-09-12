# Iteration 4.2 Runtime Contract

Status: Review

This document defines the exact runtime verification required before Iteration 4.2 can be marked Stable.

## Supported baseline

Current MODX 3.x requires PHP 8.1 or higher; PHP 8.2+ is recommended.

Recommended integration environment:

- PHP 8.2+
- MODX 3.2+
- MySQL 8.0+ or MariaDB 10.6+
- Composer
- PDO MySQL
- XML/SimpleXML/XMLWriter
- ZIP
- GD
- cURL
- fileinfo

## Verification sequence

```text
composer install
    ↓
composer build-schema
    ↓
composer verify-generated-model
    ↓
php tests/Integration/preflight.php
    ↓
php _build/build.php
    ↓
install transport package in MODX
    ↓
php tests/Integration/ModxIntegrationTest.php
    ↓
vendor/bin/phpunit
```

## Required assertions

### Composer

- dependencies resolve
- PSR-4 autoload resolves
- PHPUnit starts

### xPDO

- schema parses
- metadata exists
- MySQL maps exist
- all seven model classes load
- `addPackage()` succeeds

### Transport Package

- package is created
- package version is `0.1.0-alpha2`
- namespace `aibridge` is registered
- menu vehicle installs
- system settings install
- files install
- resolver creates all seven tables

### Manager

- Extras → AI Bridge exists
- menu action is `home`
- `AIBridgeHomeManagerController` loads
- CSS/JS assets load
- no legacy `modAction` dependency exists

### Processor

- `health` processor executes through MODX
- JSON response contains component/version/status
- processor has no embedded business logic

### Service container

- `aibridge.application` resolves
- health service returns the expected component identifier

### Database

Expected tables:

```text
modx_aibridge_tokens
modx_aibridge_audit
modx_aibridge_jobs
modx_aibridge_schemas
modx_aibridge_fingerprints
modx_aibridge_policies
modx_aibridge_snapshots
```

The `modx_` prefix is illustrative; the actual prefix comes from the target MODX installation.

## Stability rule

The iteration must remain `Review` if any runtime assertion above has not been executed against a real MODX installation.

A successful static analysis, PHP lint, or schema inspection is not sufficient evidence for `Stable`.
