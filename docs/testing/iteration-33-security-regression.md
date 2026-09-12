# Iteration 33 — Runtime Security Regression Evidence

Environment: Docker Desktop on Windows, `docker compose` stack from `docker-compose.yml` — MODX 3.2.2-pl,
PHP 8.2.33, MySQL 8.0. Commands executed inside the `modx` container.

## Scope

Runtime security regression across the REST boundary and the security services: authentication bypass,
scope escalation, profile escape, rate limiting, idempotency replay/conflict and secret leakage, plus
web-front-controller body handling (malformed JSON, oversized body).

## Changes

- `assets/components/aibridge/api/index.php` now fails closed on a malformed JSON body: a non-array JSON
  payload returns `400 invalid_json` instead of being silently treated as an empty object.

## Evidence

### In-process runtime matrix

`MODX_ROOT=/var/www/html vendor/bin/phpunit --filter SecurityRegressionRuntimeTest` → **OK (6 tests)**:

1. **Auth bypass** rejected: invalid scheme → 401 `invalid_authorization_scheme`; empty/unknown bearer →
   401; revoked token → 401 `token_inactive`; expired token → 401 `token_expired`; no token material is
   echoed in error bodies.
2. **Scope escalation** denied: read-only cannot validate or create; write-only cannot delete or publish;
   validate-only cannot update (`insufficient_scope`, 403).
3. **Profile escape** denied: a client-supplied `profile_id` in the mutation body does not override the
   token's profile — the dispatched job is persisted with the principal's profile.
4. **Rate limit**: limit 2 → third authenticated request returns 429 `rate_limited` with a positive
   `Retry-After`.
5. **Idempotency**: first `begin` accepted; same key with a different payload → `conflict`; same key and
   payload → `replay`; after `complete` the stored response is replayed.
6. **Secret leakage**: responses and all audit `context_json` contain neither the token plaintext nor its
   SHA-256 hash; the Manager token list exposes neither `token_hash` nor `token`.

### HTTP boundary (front controller)

`MODX_ROOT=/var/www/html php scripts/security-regression-http.php` → **PASS**:
`public_health` 200, `unknown_token_401`, `malformed_json_400` (`invalid_json`),
`oversized_body_413` (`request_too_large`), `valid_json_200`.

### Regression suite

- `MODX_ROOT=/var/www/html vendor/bin/phpunit --testsuite unit,contract,security` → **OK (57 tests, 181 assertions)**.
- `MODX_ROOT=/var/www/html vendor/bin/phpunit` → **OK (108 tests, 1464 assertions)**.
- `composer lint` / `verify-static-contract` / `verify-generated-model` → PASS.

## Notes / remaining

- `scripts/security-regression-http.php` is wired into `scripts/test-modx.sh` (step 11) so the full
  runtime gate exercises it.
- Observability/redaction, SDK, upgrade drill, performance, RC artifact and Stable tag remain for
  Iterations 34–39.
