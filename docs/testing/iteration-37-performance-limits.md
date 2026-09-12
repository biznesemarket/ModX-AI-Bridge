# Iteration 37 — Performance & Limits Evidence

Environment: Docker Desktop on Windows, `docker compose` stack from `docker-compose.yml` — MODX 3.2.2-pl,
PHP 8.2.33, MySQL 8.0. Harness executed inside the `modx` container against the real database.

## Scope

Measured throughput/latency of Bridge operations under volume, with per-operation budgets, plus the
existing body-limit and rate-limit behaviours.

## Changes

- Added `scripts/performance-limits.php`: measures large-resource save, many-TV save, snapshot creation,
  queue dispatch/claim depth, rate-limiter throughput and audit write/read; reports total vs per-op
  latency against a per-op budget and exits non-zero on breach.
- Wired into `scripts/test-modx.sh` (step 13).

## Evidence

`MODX_ROOT=/var/www/html php scripts/performance-limits.php` → **PERFORMANCE & LIMITS: PASS**:

```text
measurement                       total        per-op       thr/op     status
large_content_save (256 KiB)      183.39 ms    183.39 ms    2000 ms    PASS (bytes=263949)
many_tvs_save (50 TVs)            1298.33 ms   1298.33 ms   3000 ms    PASS
snapshot (256 KiB + 50 TVs)       559.86 ms    559.86 ms    2000 ms    PASS
queue_dispatch (200 jobs)         3940.51 ms   19.7 ms      50 ms      PASS (200 jobs)
queue_claim (200 jobs)            7674.78 ms   38.37 ms     50 ms      PASS (claimed=200)
rate_limiter (500 consumes)       12555.47 ms  25.11 ms     50 ms      PASS (allowed=500)
audit_record (500 events)         12533.62 ms  25.07 ms     50 ms      PASS
audit_read (100 events)           71.2 ms      71.2 ms      2000 ms    PASS
```

Related limits already certified: oversized request body → HTTP 413 (`scripts/security-regression-http.php`)
and rate limiting → 429 + `Retry-After` (`SecurityRegressionRuntimeTest`).

Post-harness regression: `phpunit` with `MODX_ROOT` → **OK (123 tests, 2128 assertions)**;
`composer lint` / `verify-static-contract` / `verify-generated-model` → PASS.

## Notes / observed limits

- Audit writes and rate-limiter consumes are xPDO/PDO round-trip bound (~25 ms/op in this container); the
  per-op budgets are set with headroom above observed values. This is an observed baseline, not a
  service-level guarantee.
- Release Candidate artifact and the Stable tag remain for Iterations 38–39; TypeScript SDK stays BLOCKED
  (no Node toolchain).
