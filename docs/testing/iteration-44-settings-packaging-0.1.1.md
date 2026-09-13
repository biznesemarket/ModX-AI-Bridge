# Iteration 44 — System Settings Packaging Fix & 0.1.1 Stable Release

Environment: local Windows host + Docker Desktop (MODX 3.2.2-pl, PHP 8.2.33, MySQL 8.0, zlib 1.3.1);
certification on GitHub Actions `ubuntu-latest` (Docker + PHP 8.2 + Composer + Node.js 24).

## Scope

While implementing reproducible builds (Iteration 43) the survey of vehicle names exposed that the transport
package contained only 3 vehicles (namespace, menu, one core file vehicle) instead of also carrying one
vehicle per system setting. Fix the defect, cut `0.1.1`, and certify it.

## Root cause (Defect #34)

`_build/elements/settings.php` built each setting as:

```php
(new modSystemSetting($modx))->fromArray([...], '', true)
```

`xPDOObject::fromArray()` returns **void** (`core/vendor/xpdo/xpdo/src/xPDO/Om/xPDOObject.php`), so the
expression evaluates to `null`. `build.php` guards with `if (!$setting instanceof modSystemSetting) continue;`,
so every setting was skipped and the package shipped no `aibridge_*` settings. Confirmed with a diagnostic:
`count=19`, index 0 is `null`. Releases `0.1.0` and earlier were affected; tests masked it because
`scripts/configure-test-runtime.php` and the E2E suites create settings on the fly, and the application falls
back to code defaults.

## Changes

- `_build/elements/settings.php`: build each setting in two steps via a `$setting()` helper so the array
  contains `modSystemSetting` objects.
- Version bump `0.1.0` → `0.1.1` across the package (`_build/config.inc.php`), runtime identity
  (`config/config.php`, `Application`, `RestApi`, `health`, `McpProtocol`, `OperationsConsoleService`,
  `scripts/readiness.php`), SDKs (`sdk/php`, `sdk/typescript` incl. lockfile) and `scripts/test-modx.sh`
  (installs `aibridge-0.1.1.transport.zip`).
- `docs/ai-agent/baseline/known-defects.md`: recorded Defect #34.

## Evidence

After the fix the package contains 22 vehicles (namespace, menu, core file vehicle, 19 settings) and, once
installed on the local stack, `modx_system_settings` has 19 `aibridge_*` rows:

```text
vehicles: 22
manifest vehicles: 22
aibridge_ keys in manifest: 20
Transport Package installation: PASS  (Signature: aibridge-0.1.1)
settings in DB: 19
MODX runtime verification: PASS
```

Reproducibility and certification (commit `db949b5`):

```text
PACKAGE REPRODUCIBILITY: PASS (aibridge-0.1.1, sha256 26ccdee1fb27097694dc735795f86e2c75a66136310e2a1e9ca248465c7a729a)
TYPESCRIPT SDK: PASS
== 15. Package reproducibility ==
PACKAGE REPRODUCIBILITY: PASS (aibridge-0.1.1, sha256 26ccdee1...)
== MODX INTEGRATION: PASS ==
STABLE certification gates passed.
```

CI run `34743692154` (release gate) and the `Quality Gates` / `MODX Integration` push runs on `db949b5` are
green. The downloaded CI artifact and the local build hash identically (`26ccdee1...`), confirming
cross-environment reproducibility.

## Outcome

`0.1.1` is the current Stable release (tag `v0.1.1`); it supersedes `0.1.0`. See
`docs/release/0.1.1.md`, `docs/release/stable-status.md`.
