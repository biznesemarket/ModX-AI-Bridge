# Transport Package

Status: Stable (`0.1.0`)

Iteration 4.2 replaces the previous placeholder builder with a MODX 3 Transport Package build path.

## Build

```bash
composer install
composer build-schema
MODX_ROOT=/absolute/path/to/modx php _build/build.php
```

The build script uses the MODX 3 namespaced transport classes and writes:

```text
core/packages/aibridge-0.1.1.transport.zip
```

A release-candidate build carries an extra suffix (for example `aibridge-0.1.0-rc1.transport.zip`); the
Stable build has no suffix.

## Reproducible builds

The builder produces a byte-reproducible archive:

- every vehicle gets a deterministic guid derived from its natural key (xPDO otherwise uses
  `md5(uniqid(rand(), true))`, which changes vehicle paths, signatures and the manifest on every run);
- the archive is rewritten with sorted file entries, a fixed modification time, and no directory entries,
  so the artifact depends only on files (git cannot track empty directories).

Set `SOURCE_DATE_EPOCH` to choose the timestamp; the default is `315532800` (1980-01-01 UTC, the minimum DOS
timestamp). Verify reproducibility with:

```bash
MODX_ROOT=/absolute/path/to/modx php scripts/verify-package-reproducibility.php
```

which builds the package twice and fails unless both archives hash identically. `scripts/test-modx.sh` runs
this as step 15.

## Installation

```bash
MODX_ROOT=/absolute/path/to/modx \
php scripts/install-package.php aibridge-0.1.1
```

The installer invokes MODX's `workspace/packages/scanlocal` and `workspace/packages/install` processors.

## Install-time operations

The Transport Package installs:

- namespace `aibridge`;
- Manager menu item;
- system settings;
- component PHP files;
- browser assets;
- xPDO model tables.

The database resolver executes only after the component files have been copied, so the namespaced xPDO classes are available before `createObjectContainer()` is called.
