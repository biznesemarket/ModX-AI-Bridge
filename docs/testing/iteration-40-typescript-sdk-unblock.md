# Iteration 40 — TypeScript SDK Gate Unblock Evidence

Environment: local Windows host with Node.js 24.15.0 / npm 11.12.1 (Git Bash) and Docker Desktop; the
single-command gate was executed on GitHub Actions `ubuntu-latest` (Docker + PHP 8.2 + Composer + Node),
which is the runner the release workflow is designed for.

## Scope

Clear the BLOCKED TypeScript SDK gate and obtain one green
`AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh` invocation.

## Root causes

- `sdk/typescript` shipped without `package-lock.json`, so `npm ci` could never succeed (`EUSAGE`). The
  toolchain being absent was only part of the problem; with Node installed the gate still failed.
- `sdk/typescript/src/client.ts` used an extensionless relative import (`./errors`) under
  `module: NodeNext` → `TS2835`.
- The default `idempotencyKey = crypto.randomUUID()` parameter inferred the narrow template-literal type
  `` `${string}-${string}-${string}-${string}-${string}` ``, rejecting ordinary string keys → `TS2345`.
- `scripts/sdk-typescript-check.sh` was committed mode `100644`, while `quality-gate.sh` and `stable-gate.sh`
  invoke it as `./scripts/sdk-typescript-check.sh`; on Linux CI this failed with exit `126`.

## Changes

- Added `sdk/typescript/package-lock.json` (lockfileVersion 3, `typescript@5.9.3`).
- `src/client.ts`: `./errors` → `./errors.js`; idempotency-key parameters typed as `string`.
- `.gitignore`: ignore `node_modules/` and `sdk/typescript/dist/`.
- `scripts/sdk-typescript-check.sh`: executable bit set in the Git index (`100755`).
- `.github/workflows/release.yml`: added `workflow_dispatch` so the full certification gate can be run on
  demand against `main`.

## Evidence

Local (Git Bash):

```text
$ bash scripts/sdk-typescript-check.sh
added 1 package in 3s
> @modx-ai-bridge/sdk@0.1.0 build
> tsc -p tsconfig.json
> @modx-ai-bridge/sdk@0.1.0 test
> tsc -p tsconfig.json --noEmit
TYPESCRIPT SDK: PASS
```

CI `release.yml` (`workflow_dispatch`, commit `642c681`, run `34715583238`):

```text
verify  (bash scripts/quality-gate.sh)                                   PASS
certify (AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh)   PASS (7m54s)
  unit,contract,security                                                 OK (58 tests, 188 assertions)
  sdk                                                                    OK (10 tests, 49 assertions)
  TYPESCRIPT SDK: PASS
  == MODX INTEGRATION: PASS ==                                           OK (123 tests, 626 assertions)
  STABLE certification gates passed.
package                                                                  PASS
```

`Quality Gates` and `MODX Integration` push runs on `642c681` are also green.

## Outcome

The TypeScript SDK gate is PASS and the single-command Stable gate is green. Stable artifact finalization and
tagging follow in Iteration 41 / `docs/release/0.1.0.md`.
