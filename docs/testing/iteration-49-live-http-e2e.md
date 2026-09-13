# Iteration 49 — TypeScript SDK Live HTTP E2E

## Scope

The TypeScript SDK was covered by a build, a type-level contract and runtime tests with a fake `fetch`. The
SDK had never been executed against a real `/api/ai/v2/*` endpoint, so transport-level mismatches between the
client assumptions and the server envelopes could go unnoticed (and one did, see Defect #35).

## Design

- `scripts/ts-live-runtime.php` (container, test/dev only): applies the test runtime settings, reuses the
  `ts-live-e2e` profile, revokes its previous tokens, issues a fresh scoped token
  (`site:read`, `content:validate`, `resource:write`, `resource:preview`, `resource:delete`,
  `resource:publish`), finds/creates a template and prints a single JSON context on stdout. `--restore-allowlist`
  puts `aibridge_ip_allowlist` back to `["127.0.0.1"]`. The token is only ever passed via the environment to
  the Node process; it is never echoed.
- `scripts/ts-live-check.sh` (host): requires a running compose stack and Node.js, resolves the MODX container
  gateway with `docker inspect`, widens the allowlist to `["127.0.0.1","<gateway>"]` (a host-initiated request
  reaches Apache from the bridge gateway, not loopback — verified: without the gateway the API answers
  `403 ip_not_allowed`), builds the SDK, runs a stop-file-driven `worker.php --once` loop for the queue, then
  executes the live suite and restores the loopback-only allowlist via an `EXIT` trap. Under Git Bash
  (`cygpath` present) it disables MSYS argument rewriting and passes the compose file as a Windows path.
- `sdk/typescript/tests/live/live.test.mjs`: 11 `node:test` cases against the real service (health, 401 with
  an unknown token, capabilities, profile isolation, schema/fingerprint, content contract, validation,
  resource create → queue → worker → `resource_id`, update round-trip, delete disabled, publish
  approval-gated, MCP initialize/tools/tool call/resource read).
- `sdk/typescript/package.json`: `npm test` stays offline (`tests/runtime/client.test.mjs`);
  `npm run test:live` runs the live suite. `scripts/test-modx.sh` runs the live check as step 10b, so both
  the `Release` gate and every `MODX Integration` run exercise it. `modx-integration.yml` installs Node 24
  (pinned) for that job.

## Defect #35 (found by this E2E)

`waitForJob()` in both SDKs looked only at `data.status`/`status`, but the real
`GET /api/ai/v2/jobs/{id}` response is `{success: true, job: {status: ...}}`. Polling never saw a terminal
state and timed out. Fixed in `sdk/php/src/Client.php` and `sdk/typescript/src/client.ts`
(`data.status → job.status → status`), with runtime tests that lock the real envelope shape.

## Evidence

Local run against the Docker stack (MODX 3.2.2-pl, PHP 8.2.33):

```text
$ bash scripts/sdk-typescript-check.sh
✔ waitForJob reads the real /jobs envelope (success.job.status)
ℹ tests 12 / pass 12 / fail 0
TYPESCRIPT SDK: PASS

$ bash scripts/ts-live-check.sh
✔ health is public and reports the running version
✔ capabilities rejects an unknown token over HTTP
✔ capabilities with the scoped token exposes REST and MCP
✔ profiles are isolated to the live profile
✔ site schema and fingerprint are discoverable
✔ content contract is built for a template
✔ content validation accepts valid content and reports errors
✔ resource create flows through the queue and returns a resource id
✔ resource update flows through the queue
✔ delete stays disabled and publish stays approval-gated
✔ MCP initialize, tools, tool call and resource read work over HTTP
ℹ tests 11 / pass 11 / fail 0
TYPESCRIPT SDK LIVE HTTP: PASS
```

After the run the allowlist is back to `["127.0.0.1"]` and no worker process remains (verified).
Deterministic suites stay green (`unit,contract,security` 58 tests; `sdk` 11 tests).

## Notes

- `resource.delete` is disabled by policy by design, so the live E2E leaves the resources it creates in a
  persistent local stack; CI stacks are torn down (`docker compose down -v`) by `test-modx.sh`.
- The live context script is test/dev only and must never run against production: it writes test runtime
  settings and issues a token.
