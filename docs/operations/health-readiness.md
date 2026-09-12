# Health and Readiness

Health and readiness are intentionally different.

**Health** answers whether the process can execute basic Bridge code.

**Readiness** answers whether the instance can safely accept work. It must verify the MODX bootstrap, Bridge namespace/service registration, database connectivity, and required schema state.

Recommended endpoints:

- `/health` — liveness, no secrets;
- `/ready` — dependency/readiness state, no secrets.

HTTP status:

- `200` when ready;
- `503` when not ready.

Readiness failures must be machine-readable and must not expose credentials or internal configuration values.
