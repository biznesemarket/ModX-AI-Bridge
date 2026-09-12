# Backup and Restore Runbook

A production backup must cover at least:

- MODX database;
- `core/components/aibridge/` persistent configuration/data where applicable;
- uploaded assets affected by Bridge operations;
- deployment metadata.

Recommended sequence:

```text
stop or quiesce mutation workers
→ database dump
→ verify dump exists
→ record checksum
→ snapshot required files
→ resume workers
```

Restore sequence:

```text
stop workers/API mutations
→ restore database
→ restore application/package/files if required
→ verify MODX bootstrap
→ verify Bridge schema/migrations
→ clear cache
→ readiness check
→ smoke test
→ resume workers
```

A backup is not considered operationally verified until a restore test has succeeded in an isolated environment.
