# Iteration 48 — `0.1.2` Patch Release

## Scope

`main` carried unreleased Iterations 45–47 (deterministic provisioning marker, TypeScript SDK runtime tests,
supply-chain pinning). Cut them as the patch release `0.1.2`: bump every version identity, add the CHANGELOG
section, and certify the new transport package.

## Design

The version is duplicated by design (package identity, runtime identity, SDK client identity), so the bump
touches all of them:

- build/package: `_build/config.inc.php` (drives `aibridge-0.1.2.transport.zip` and the package signature);
- runtime identity: `core/components/aibridge/config/config.php`, `Application::health()`,
  `RestApi` `/health`, `processors/health.class.php`, `McpProtocol::initialize()` serverInfo,
  `OperationsConsoleService::overview()` and `scripts/readiness.php` fallbacks;
- SDKs: `sdk/php/src/Client.php`, `sdk/typescript/src/client.ts` (`McpClient.initialize`), `sdk/typescript/package.json`
  + `package-lock.json`, and the runtime-test assertion;
- installer path: `scripts/test-modx.sh` installs `aibridge-0.1.2.transport.zip`.

The `User-Agent` stays `modx-ai-bridge-sdk-{php,ts}/0.1` (major.minor convention, unchanged by a patch).

## Local verification

```text
composer validate --no-check-publish --strict        valid
composer lint                                        no syntax errors
composer verify-static-contract                      Static contract: PASS
composer test -- --testsuite unit,contract,security  OK (58 tests, 188 assertions)
composer test -- --testsuite sdk                     OK (10 tests, 49 assertions)
bash scripts/sdk-typescript-check.sh                 TYPESCRIPT SDK: PASS (11/11 runtime tests)
bash scripts/verify-supply-chain-pins.sh             SUPPLY CHAIN PINS: PASS

# container: build + install + runtime
Created new transport package with signature: aibridge-0.1.2
Built: /var/www/html/core/packages/aibridge-0.1.2.transport.zip
Transport Package installation: PASS (Signature: aibridge-0.1.2)
MODX runtime verification: PASS (namespace, xPDO models, service container, menu, processor)
SELECT COUNT(*) ... aibridge_%                        19
PACKAGE REPRODUCIBILITY: PASS (aibridge-0.1.2, sha256 b1b985bffdd3a661bd3f99cfa1b333fee854a5339b0fb0d34468e1575b9acf94)
# pre-release tree; after the README release update the tagged tree reproduces eedd5f64… (see Packaging note)
```

## Certification

Pre-release `Release` dispatch on `main`, commit `ad92ab5`, run `34748277073`: `verify` PASS (incl.
`SUPPLY CHAIN PINS: PASS`), `certify` PASS, `package` PASS; artifact sha256 `b1b985bf…` (139544 bytes).

Release commit `c8ac594` (tag `v0.1.2`), tag run `34748489085`: all three jobs PASS again, including the full
`AIBRIDGE_RUNTIME=1` gate:

```text
Transport Package installation: PASS (Signature: aibridge-0.1.2)
MODX runtime verification: PASS
OK (123 tests, 626 assertions)
PACKAGE REPRODUCIBILITY: PASS (aibridge-0.1.2, sha256 eedd5f643fe97b378f5a4304fb3296b61a88db045d78302887417d88f8cb582d)
STABLE certification gates passed.
```

A local rebuild of the tagged tree reproduces `eedd5f64…` exactly. Evidence artifacts:
`release-evidence-ad92ab5…` (99476 bytes) and `release-evidence-c8ac594…` (99525 bytes), 90 days.

## Packaging note (process)

`_build/build.php` embeds the repository `README.md` into the package manifest. The release record updates
README from `0.1.1` to `0.1.2`, so the artifact hash changes between the pre-release certification and the
tagged tree (`b1b985bf…` → `eedd5f64…`); the tag run re-certifies the final bytes. **Process rule for the next
release:** update every version identity, including `README.md`, in the bump commit *before* dispatching the
gate, so the certified artifact and the tag run produce the same hash (as happened for `0.1.1`).

## Notes

- No schema or migration change: migration level stays `001_initial.sql`, `002_approval_workflow.sql`.
- No API contract change; `0.1.2` supersedes `0.1.1` (`v0.1.1` records stay historical and untouched).
