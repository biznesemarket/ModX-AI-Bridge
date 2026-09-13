# Iteration 62 — Manager Processor Coverage and Dependency Review

## Scope

1. **Processor-level coverage** for the remaining `AdminProcessor` descendants: `ResourcesProcessor`,
   `OverviewProcessor` and `ActionProcessor`.
2. **Read-back filters**: no concrete request arrived, so nothing was changed (the existing filter surface is
   recorded in `docs/ai-agent/HANDOFF.md` §4).
3. **Dependabot review** of the open pull request.

## Design

### ManagerProcessorsTest

The new integration test boots a real, initialized `modX` (anonymous subclass with a toggleable
`hasPermission()`), sets `$modx->error` explicitly (the CLI bootstrap does not create it) and requires the
installed `processors/manager/*.class.php` files from the component namespace path.

- **Permission guard on all three processors**: `manager_permission_required` without `aibridge_manage` and
  `manager_auth_required` when `$modx->user` is not a manager.
- **`ResourcesProcessor` modes**: `tree` (ordering by `menuindex`, `depth=1` nesting, `has_children`),
  `search` and the unknown-mode fallback, `get` (found / `not_found`), `contract`, `qa` and
  `fingerprint_diff` (missing fingerprint → `valid=false`, `fingerprint_not_found`).
- **`OverviewProcessor`**: all console sections present, readiness `ready`, and every section bounded by the
  console's 50-row limit while still containing the freshly created rows.
- **`ActionProcessor`**: `id`/`status` validation, unknown entity rejection, invalid status rejection, and the
  profile/token/policy status transitions against the database.

### Dependabot review

Open PR #1 bumps `docker/modx/Dockerfile` from `php:8.2-apache@sha256:0970c7c1…` to
`php:8.5-apache@sha256:609de4ea…` (tag and digest stay pinned).

**Review outcome: not merged.** The MODX 3.2.2-pl runtime has only been certified on PHP 8.2 (all gates and
the 0.9.1 evidence), the CI static gates pin PHP 8.2, and PHP 8.5 compatibility for MODX core is not
validated. Merging would silently switch the integration/certification runtime and invalidate the current
evidence. Recommendation: revisit when MODX supports PHP 8.5; optionally `@dependabot ignore this major
version`. The PR is left open with this decision recorded.

## Evidence

Local run against the Docker stack (MODX 3.2.2-pl, PHP 8.2.33), Stable `0.9.1` unchanged:

```text
php -l tests/Integration/ManagerProcessorsTest.php   No syntax errors
phpunit --filter ManagerProcessorsTest               OK (4 tests, 61 assertions)
MODX_ROOT=... vendor/bin/phpunit                     OK (162 tests, 7206 assertions)
  (unit,contract,security 63, sdk 11, integration 88)
```

## Compatibility and security

- Tests and documentation only: no `core/`/`assets/` runtime change, so the transport package (which ships
  `core/` + `assets/` only) is byte-identical and no version bump/tag/release is required. Stable stays
  `0.9.1` (`docs/release/0.9.1.md`).
- The new tests assert the existing security boundary (manager authentication + `aibridge_manage`) and change
  nothing in `SecurityDecisionPipeline`, policies or scopes.
- The Dependabot decision above is fail-closed with respect to certification: no unverified runtime upgrade.

## Status

Coverage complete; no defects found in the three processors. Dependabot PR reviewed and intentionally not
merged. No release: iteration changes tests/docs only.
