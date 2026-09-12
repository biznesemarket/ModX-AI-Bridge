# Iteration 4.3 — Reproducible MODX Test Environment

Status: Review

## Purpose

Iteration 4.3 provides a reproducible environment for validating ModX AI Bridge against a real MODX 3.x runtime.

The purpose is not to add product functionality. It is to establish an executable integration gate.

## Environment

```text
Docker Compose
├── PHP 8.2 + Apache
├── MySQL 8.0
└── MODX 3.x
        │
        └── ModX AI Bridge
```

MODX currently requires PHP 8.1+ and recommends PHP 8.2+, with MySQL 8.0+ / MariaDB 10.6+ recommended. Required PHP extensions include curl, dom, fileinfo, gd, json, pdo, simplexml, xml, xmlwriter, zip, zlib and PDO MySQL for MySQL. citeturn0search0

## Test command

From the repository root:

```bash
chmod +x scripts/test-modx.sh
./scripts/test-modx.sh
```

The test stack can be retained for debugging:

```bash
KEEP_MODX_STACK=1 ./scripts/test-modx.sh
```

Stop and remove it:

```bash
docker compose down -v
```

## Execution sequence

```text
PHP preflight
      ↓
Docker build
      ↓
MySQL health
      ↓
MODX project creation
      ↓
MODX CLI installation
      ↓
Bridge Composer installation
      ↓
schema/model verification
      ↓
Transport Package build
      ↓
Transport Package installation
      ↓
namespace verification
      ↓
xPDO model verification
      ↓
service container verification
      ↓
Manager menu verification
      ↓
processor verification
      ↓
PHPUnit
```

MODX supports CLI installation using `setup/config.xml` and `php ./index.php --installmode=new`; the repository's installer follows this documented mechanism rather than relying on browser automation. citeturn1search0

## Important implementation boundary

This repository does not fabricate generated xPDO maps or claim runtime success when MODX is absent.

`composer build-schema` currently verifies the authoritative schema and model destination. Actual xPDO code generation remains a toolchain operation in the MODX development environment and must be committed only after the generated output has been verified.

## Runtime assertions

The runtime verifier checks:

- MODX instance creation
- Manager context initialization
- `aibridge` namespace
- PSR-4 loader
- xPDO package registration
- all seven model mappings
- `aibridge.application` service
- application health
- Manager menu
- `health` MODX processor

## Stability gate

Iteration 4.3 is `Stable` only when the complete command exits with:

```text
== MODX INTEGRATION: PASS ==
```

A successful static CI job alone is insufficient.

## Why this gate exists

The project architecture contains several MODX-specific assumptions that cannot be proved by PHP linting:

- Transport Package vehicle construction
- resolver execution
- package installation
- namespace registration
- xPDO model loading
- database table creation
- Manager controller loading
- processor registration
- service container behavior

Those assumptions must be exercised by a real MODX runtime.

## Official MODX references

- Server requirements: current MODX 3.x requires PHP 8.1+, with PHP 8.2+ recommended. citeturn0search0
- Current Extra build structure: `_build/build.php`, `_build/config.inc.php`, `elements/`, `resolvers/`. citeturn0search1
- Composer installation: MODX documents `composer create-project modx/revolution www 3.x-dev` for developer installations. citeturn0search6
- CLI installation: MODX documents `setup/config.xml` and `--installmode=new`. citeturn1search0
