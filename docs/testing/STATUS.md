# Testing Status — Iterations 21–59

**Status: STABLE — `0.8.0` (`v0.8.0`), superseding `0.7.1`, `0.7.0`, `0.6.0`, `0.5.0`, `0.4.0`, `0.3.0`, `0.2.0`, `0.1.3`, `0.1.2`, `0.1.1` and `0.1.0`; all gates PASS on the CI runner**

Verified against a real Docker stack (MODX 3.2.2-pl, PHP 8.2.33, MySQL 8.0) and certified with a single
`AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh` run on GitHub Actions `ubuntu-latest`
(Docker + PHP + Composer + Node):

- `composer validate --no-check-publish --strict` — PASS
- `composer lint` — PASS
- `composer verify-static-contract` — PASS
- `composer verify-generated-model` — PASS
- `phpunit --testsuite unit,contract,security` — PASS (58 tests, 188 assertions)
- `phpunit --testsuite sdk` — PASS (10 tests, 49 assertions)
- `phpunit` with `MODX_ROOT` — PASS (123 tests, integration + sdk included)
- `scripts/verify-modx-runtime.php` — PASS
- `scripts/test-queue-concurrency.php` — PASS (forked race: exactly one winner)
- REST transport smoke over Apache — health 200, unauthenticated capabilities 401, ready 200
- Migration runner — idempotent (`skipped ... already applied` on rerun)
- Worker CLI `--once` — PASS
- Mutation E2E + verification/rollback — PASS. Evidence: `docs/testing/iteration-30-mutation-rollback.md`
- Approval workflow E2E — PASS. Evidence: `docs/testing/iteration-31-approval-workflow.md`
- Multi-site isolation E2E — PASS. Evidence: `docs/testing/iteration-32-multi-site-isolation.md`
- Security regression runtime — PASS. Evidence: `docs/testing/iteration-33-security-regression.md`
- Observability/readiness — PASS. Evidence: `docs/testing/iteration-34-observability.md`
- SDK certification — PHP PASS; TypeScript PASS (Iteration 40, `TYPESCRIPT SDK: PASS`). Evidence:
  `docs/testing/iteration-35-sdk-certification.md`, `docs/testing/iteration-40-typescript-sdk-unblock.md`
- Upgrade/recovery drill — PASS. Evidence: `docs/testing/iteration-36-upgrade-recovery.md`
- Performance & limits — PASS (`scripts/performance-limits.php`: 256 KiB resource, 50 TVs, queue depth
  200, rate limiter, audit growth; per-op budgets). Evidence:
  `docs/testing/iteration-37-performance-limits.md`
- Release Candidate `0.1.0-rc1` — BUILT and content-verified; SHA-256 and metadata recorded
  (`docs/release/0.1.0-rc1.md`, `scripts/release-candidate.php`). Evidence:
  `docs/testing/iteration-38-release-candidate.md`
- Stable certification attempt (Iteration 39) — NOT STABLE (historical): gate exited 3 at the TypeScript SDK
  gate. Evidence: `docs/testing/iteration-39-stable-certification.md`
- Iteration 40 — TypeScript SDK gate cleared, single-command stable gate green on commit `642c681`
  (run `34715583238`). Evidence: `docs/testing/iteration-40-typescript-sdk-unblock.md`
- Iteration 41 — Stable `0.1.0` package finalized and re-certified on commit `160a659`
  (run `34716441523`); `STABLE certification gates passed.`; tag `v0.1.0`. Evidence:
  `docs/testing/iteration-41-stable-release.md`, `docs/release/0.1.0.md`
- Iteration 42 — CI hardening (`deterministic` job runs the `sdk` suite + TypeScript gate; Node 24 action
  runtimes; release-evidence artifact upload).
- Iteration 43 — reproducible transport packages (`PACKAGE REPRODUCIBILITY: PASS`, step 15 of test-modx);
  local and CI builds hash identically (`docs/development/transport-package.md`).
- Iteration 44 — Defect #34 fixed: the package now ships all 19 `aibridge_*` system settings; `0.1.1`
  certified on commit `db949b5` (run `34743692154`, `PACKAGE REPRODUCIBILITY: PASS`,
  `STABLE certification gates passed.`); tag `v0.1.1`. Evidence:
  `docs/testing/iteration-44-settings-packaging-0.1.1.md`, `docs/release/0.1.1.md`
- Iteration 45 — deterministic MODX provisioning readiness: `.modx-ready` sentinel removes the
  `setup/config.xml` race in `test-modx.sh`. Evidence:
  `docs/testing/iteration-45-provisioning-readiness.md`
- Iteration 46 — TypeScript SDK runtime tests (11 tests, `node --test`, no new dependencies). Evidence:
  `docs/testing/iteration-46-typescript-runtime-tests.md`
- Iteration 47 — supply-chain pinning: container images by digest (`php:8.2-apache`, `composer:2`,
  `mysql:8.0`), GitHub Actions by commit SHA, `.github/dependabot.yml` for weekly pin updates; compose config
  and image build verified on the pinned toolchain. Evidence:
  `docs/testing/iteration-47-supply-chain-pinning.md`
- Iteration 48 — `0.1.2` patch release: version bump across package/runtime/SDK identity, CHANGELOG section,
  full gate green on `ad92ab5` (run `34748277073`) and again on the tagged `c8ac594` (tag run `34748489085`;
  `PACKAGE REPRODUCIBILITY: PASS`, sha256 `eedd5f64…`; `STABLE certification gates passed.`); tag `v0.1.2`.
  Note: the package embeds `README.md` in its manifest, so the release README update changes the artifact hash
  (pre-release `b1b985bf…` → release `eedd5f64…`). Evidence:
  `docs/testing/iteration-48-release-0.1.2.md`, `docs/release/0.1.2.md`
- Iteration 49 — TypeScript SDK live HTTP E2E: 11 tests against the real REST/MCP boundary through a queue
  worker (auth, capabilities, profile isolation, schema/fingerprint, contract/validation, create/update via
  the queue, delete/publish denials, MCP), wired into `test-modx.sh` step 10b; found and fixed Defect #35
  (`waitForJob()` envelope mismatch in both SDKs). Evidence: `docs/testing/iteration-49-live-http-e2e.md`
- Iteration 50 — `0.1.3` patch release: all version identities including README bumped before the gate
  (hash stable across runs), full gate green on `93fa4fd` (run `34751162661`; `TYPESCRIPT SDK LIVE HTTP: PASS`,
  `PACKAGE REPRODUCIBILITY: PASS`, sha256 `0d66d833…`, `OK (124 tests, 627 assertions)`,
  `STABLE certification gates passed.`); tag `v0.1.3`. Evidence:
  `docs/testing/iteration-50-release-0.1.3.md`, `docs/release/0.1.3.md`
- Iteration 51 — resource read-back API + `aibridge_version`: `GET /api/ai/v2/resources/{id}` (scope
  `resource:read`, `resource_not_found` -> 404), `getResource()` in the PHP/TS SDKs, the `resource_read` MCP
  tool, the `aibridge_version` setting (20 settings) reported by `/health` and `/ready`; the live E2E verifies
  the state after an update. Evidence: `docs/testing/iteration-51-readback-api.md`
- Iteration 52 — `0.2.0` minor release: all version identities (including README and the SDK `User-Agent`
  0.1 -> 0.2) committed in the bump commit; gate green on `350fc28` (run `34753117255`;
  `TYPESCRIPT SDK LIVE HTTP: PASS`, `PACKAGE REPRODUCIBILITY: PASS`, sha256 `b8525cda…`,
  `OK (129 tests, 650 assertions)`, `STABLE certification gates passed.`); tag `v0.2.0`. Evidence:
  `docs/testing/iteration-52-release-0.2.0.md`, `docs/release/0.2.0.md`
- Iteration 53 — `0.3.0` minor release: template variables in the read-back projection (`tvs` map on
  `GET /resources/{id}` and the `resource_read` MCP tool), `resource.read` in the capability catalog and typed
  TypeScript SDK read-back (`ResourceProjection`/`ResourceReadResponse`); all version identities (including
  README, `aibridge_version` and the SDK `User-Agent` `0.2` -> `0.3`) committed before the gate. Gate green on
  `dc3bd8a` (run `34756679369`; `TYPESCRIPT SDK LIVE HTTP: PASS`, `PACKAGE REPRODUCIBILITY: PASS`, sha256
  `9c3e547d…`, `OK (130 tests, 663 assertions)`, `STABLE certification gates passed.`); tag `v0.3.0`. Evidence:
  `docs/testing/iteration-53-readback-tvs.md`, `docs/release/0.3.0.md`
- Iteration 54 — `0.4.0` minor release: filtered, paginated resource list (`GET /api/ai/v2/resources`,
  `resource.list`, summary projection with `count`/`total`/`limit`/`offset`, whitelisted filters,
  `resource_list` MCP tool, `listResources()` in both SDKs, capability entry) with two xPDO query fixes
  (options via `newQuery()`, grouped OR search). All version identities (including README, `aibridge_version`
  and the SDK `User-Agent` `0.3` -> `0.4`) committed before the gate. Gate green on `07f3c72` (run
  `34758755913`; `TYPESCRIPT SDK LIVE HTTP: PASS`, `PACKAGE REPRODUCIBILITY: PASS`, sha256 `778d061a…`,
  `OK (135 tests, 700 assertions)`, `STABLE certification gates passed.`); tag `v0.4.0`. Evidence:
  `docs/testing/iteration-54-resource-list.md`, `docs/release/0.4.0.md`
- Iteration 55 — `0.5.0` minor release: MCP resource template `modx://resource/{id}` (`resources/read`
  resolves URI templates under `resource.read`; `-32002` for missing resources), `resources/templates/list`,
  `resourceTemplates()` in both MCP clients and `McpServer::capabilities()`. All version identities (including
  README, `aibridge_version` and the SDK `User-Agent` `0.4` -> `0.5`) committed before the gate. Gate green on
  `1796c5d` (run `34761433276`; `TYPESCRIPT SDK LIVE HTTP: PASS`, `PACKAGE REPRODUCIBILITY: PASS`, sha256
  `48c8b233…`, `OK (138 tests, 713 assertions)`, `STABLE certification gates passed.`); tag `v0.5.0`. Evidence:
  `docs/testing/iteration-55-mcp-resource-uri.md`, `docs/release/0.5.0.md`
- Iteration 56 — `0.6.0` minor release: `tv_name`/`tv_value` filter for `GET /api/ai/v2/resources` and the
  `resource_list` MCP tool (explicit TV overrides; `tv_value` requires `tv_name`; unknown TV → `invalid_filter`)
  with the xPDO partial-select fix. All version identities (including README, `aibridge_version` and the SDK
  `User-Agent` `0.5` -> `0.6`) committed before the gate. Gate green on `bb3f621` (run `34762964883`;
  `TYPESCRIPT SDK LIVE HTTP: PASS`, `PACKAGE REPRODUCIBILITY: PASS`, sha256 `19c1398b…`,
  `OK (139 tests, 732 assertions)`, `STABLE certification gates passed.`); tag `v0.6.0`. Evidence:
  `docs/testing/iteration-56-list-tv-filter.md`, `docs/release/0.6.0.md`
- Iteration 57 — `0.7.0` minor release: available contexts in `/capabilities` (`key`/`name`/`description`) and a
  join-based TV list filter (no `contentid` materialization; `count`/`total` single aggregate); `publishedon`
  sort confirmed and covered. All version identities (including README, `aibridge_version` and the SDK
  `User-Agent` `0.6` -> `0.7`) committed before the gate. Gate green on `82e9917` run `34764596898` after a
  rerun (first attempt: MODX-readiness flake at step 3, before install; `gh run rerun --failed` then passed):
  `TYPESCRIPT SDK LIVE HTTP: PASS`, `PACKAGE REPRODUCIBILITY: PASS`, sha256 `63df4013…`,
  `OK (139 tests, 736 assertions)`, `STABLE certification gates passed.`; tag `v0.7.0`. Evidence:
  `docs/testing/iteration-57-readback-polish.md`, `docs/release/0.7.0.md`
- Iteration 58 — `0.7.1` patch release: fixed `ResourceExplorerService::search()` (flat `OR:` matched almost
  everything; query options were the cache flag so `limit`/`sortby` ignored; TV filter now a join),
  `ResourceExplorerService::tree()` and `RestApi::profiles()` (limits/ordering now applied). New
  `ResourceExplorerServiceTest` (3 tests). All version identities committed before the gate. Gate green on
  `0223e5e` (run `34766913833`; `TYPESCRIPT SDK LIVE HTTP: PASS`, `PACKAGE REPRODUCIBILITY: PASS`, sha256
  `f9ee3089…`, `OK (142 tests, 744 assertions)`, `STABLE certification gates passed.`); tag `v0.7.1`. Evidence:
  `docs/testing/iteration-58-manager-xpdo-fixes.md`, `docs/release/0.7.1.md`
- Iteration 59 — `0.8.0` minor release: `context_key`/`template_id` filters (and a real `limit`) for the
  site-schema resource list; integration coverage for `AdminProcessor` (3 branches) and
  `ResourceExplorerService::get/contract/qa/fingerprintDiff`; `publishedon` summary assertion. All version
  identities committed before the gate. Gate green on `d49c044` (run `34769066811`;
  `TYPESCRIPT SDK LIVE HTTP: PASS`, `PACKAGE REPRODUCIBILITY: PASS`, sha256 `c0b96adb…`,
  `OK (149 tests, 783 assertions)`, `STABLE certification gates passed.`); tag `v0.8.0`. Evidence:
  `docs/testing/iteration-59-manager-coverage-site-filters.md`, `docs/release/0.8.0.md`

No remaining gates: TypeScript SDK PASS (build + runtime tests), package reproducibility PASS, settings
packaging fixed, provisioning deterministic, and the
single-command `AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh` passes on a host/CI runner with
Docker. A missing runtime remains a failure of certification, not a pass.
