# Iteration 4.3-r2 — Reproducible MODX Test Environment

Status: **Review**

This revision closes the main reproducibility gaps identified in Iteration 4.3.

## Changes

- MODX test version is pinned to the official 3.2.2-pl release.
- The repository is mounted read/write in Docker so Composer, xPDO generation and package building can modify the working tree where expected.
- The Docker image includes Python 3, required by the test installer no longer relying on an incomplete template mutation.
- The MODX CLI installer writes a complete `setup/config.xml`, including database, manager credentials and runtime paths.
- `scripts/generate-schema.php` now invokes the xPDO 3 CLI bundled with the installed MODX core instead of only checking that the schema exists.
- Model verification remains a hard gate and requires `metadata.mysql.php`.

## Runtime basis

MODX 3.2 requires PHP 8.1 or newer; the test image uses PHP 8.2. The official MODX changelog identifies 3.2.2-pl as the July 7, 2026 release. xPDO 3 documentation specifies `core/vendor/bin/xpdo parse-schema`, namespaced PSR-4 models, `metadata.mysql.php`, and platform maps as the expected generated output.

## Remaining status

The environment was prepared and statically validated in the current execution environment. Docker runtime execution is still required to mark this iteration Stable. No claim of successful MODX package installation is made until the complete Docker pipeline has actually run.
