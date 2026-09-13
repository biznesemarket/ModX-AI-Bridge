# Iteration 45 — Deterministic MODX Provisioning Readiness

## Scope

Remove the intermittent runtime-gate failure observed on commit `6780a0a`:
`scripts/modx-install.sh: line 16: /var/www/html/setup/config.xml: No such file or directory`.

## Root cause

`docker/modx/entrypoint.sh` materializes MODX with `composer create-project` into `/tmp/modx` and then
`cp -a /tmp/modx/. /var/www/html/`. `scripts/test-modx.sh` waited only for `/var/www/html/config.core.php`,
which is copied part-way through the tree; the next step then ran `scripts/modx-install.sh` while `setup/`
had not been copied yet. The race is timing-dependent — the same commit passed on parallel runs, and a rerun
of the failed job passed (attempt 2).

## Changes

- `docker/modx/entrypoint.sh`: remove any stale marker, then `touch /var/www/html/.modx-ready` only after the
  MODX file tree is fully materialized (existing install or completed `cp -a`).
- `scripts/test-modx.sh` step 3: wait for `.modx-ready` (90 × 2s) and fail with an explicit message if the
  marker never appears, instead of proceeding into the install with an incomplete tree.

## Evidence

Local (existing MODX in the volume, container recreated):

```text
docker compose up -d --build modx
MODX READY MARKER: PRESENT
MODX runtime verification: PASS
```

CI on commit `0b97dcd`:

```text
MODX Integration   success (2m47s; bash scripts/test-modx.sh)
Quality Gates      success (AIBRIDGE_RUNTIME=1 bash scripts/quality-gate.sh)
```

No `setup/config.xml` failure; the marker is created before the gate proceeds.
