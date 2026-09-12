# Iteration 38 — Release Candidate Evidence

Environment: Docker Desktop on Windows, `docker compose` stack from `docker-compose.yml` — MODX 3.2.2-pl,
PHP 8.2.33, MySQL 8.0. Commands executed inside the `modx` container.

## Scope

Build the `0.1.0-rc1` transport package, verify its contents, record the SHA-256 and release metadata.

## Changes

- Added `scripts/release-candidate.php`: builds via `_build/build.php`, verifies required archive entries,
  computes SHA-256/size, collects MODX/PHP/migration-level metadata and writes
  `dist/<package>.release.json` + `<package>.sha256`.
- Wired into `scripts/test-modx.sh` (step 14).
- Added `docs/release/0.1.0-rc1.md` release record.

## Evidence

`MODX_ROOT=/var/www/html php scripts/release-candidate.php` →

```text
RELEASE CANDIDATE: aibridge-0.1.0-rc1
  component: modx-ai-bridge
  version: 0.1.0
  release: rc1
  package: aibridge-0.1.0-rc1.transport.zip
  sha256: de946c39a13b7f7c399abbfe806af59d2786a59b9c2f593aaca9b092303f9a7f
  size_bytes: 141787
  signed: false
  note: No signing key is configured; integrity is provided by the recorded SHA-256.
  modx_version: 3.2.2-pl
  php_version: 8.2.33
  migration_level: 001_initial.sql, 002_approval_workflow.sql
  built_at: 2026-09-12T19:42:49+00:00
```

The package is installed successfully by `scripts/install-package.php` on the Docker stack (used throughout
iterations 30–37), and `scripts/verify-modx-runtime.php` passes afterwards.

## Notes / remaining

- Transport archives are not byte-reproducible (embedded timestamps), so the recorded SHA-256 identifies this
  build; regenerate metadata for every new artifact.
- `Stable` is not declared: TypeScript SDK certification is BLOCKED (no Node toolchain). Iteration 39 runs
  `stable-gate.sh`, which must remain NOT STABLE until that BLOCKED gate clears.
