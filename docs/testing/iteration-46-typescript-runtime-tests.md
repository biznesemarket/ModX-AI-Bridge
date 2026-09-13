# Iteration 46 — TypeScript SDK Runtime Tests

## Scope

The TypeScript SDK was covered only by `tsc` build and a type-level contract (`tests/client.contract.ts`).
Add runtime tests that exercise the compiled client.

## Design

- Tests are plain ESM (`sdk/typescript/tests/runtime/client.test.mjs`) and import the built artifact
  (`../../dist/src/index.js`), so they validate exactly what consumers load.
- `node:test` + `node:assert/strict`; no new npm dependencies and no DOM/Node type conflicts.
- `package.json` `test` is now `node --test`; `scripts/sdk-typescript-check.sh` still runs `npm ci`,
  `npm run build` (type-checks and emits) and `npm test`.

Covered behaviour:

- request URL/base-URL trimming, `POST`/`PATCH`/`DELETE` methods, `Authorization: Bearer`,
  `Accept`/`Content-Type`/`User-Agent` headers, JSON body;
- `Idempotency-Key` on create/update/delete/publish, `updateResource` id merge, publish `approval_id`;
- `siteSchema` query string and optional `contentContract` `template_id`;
- error mapping to `ApiError` (status/code/details) and non-JSON body fallback;
- `waitForJob` polling until terminal state and 408 `job_timeout`;
- `McpClient` JSON-RPC envelopes with incrementing ids and `clientInfo.version`.

## Evidence

```text
$ bash scripts/sdk-typescript-check.sh
✔ request sends auth and JSON headers and trims the base URL
✔ createResource sends an Idempotency-Key
✔ updateResource merges the id into the body
✔ deleteResource sends no body
✔ publishResource sends the approval_id
✔ siteSchema and contentContract build query strings
✔ non-ok responses map to ApiError
✔ non-JSON error bodies fall back to a generic message
✔ waitForJob polls until a terminal state
✔ waitForJob throws a 408 ApiError on timeout
✔ McpClient wraps calls in JSON-RPC envelopes with incrementing ids
ℹ tests 11
ℹ pass 11
ℹ fail 0
TYPESCRIPT SDK: PASS
```

## Notes

- The tests use a fake `fetch`; they do not perform real HTTP against a running MODX. End-to-end coverage of
  the SDK against the live API remains an integration concern (PHP SDK / runtime suites cover the server side).
