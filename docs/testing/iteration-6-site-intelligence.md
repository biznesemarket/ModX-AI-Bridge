# Iteration 6 — Site Intelligence

**Status:** Review

Iteration 6 replaces the discovery stubs with the first functional, read-only Site Intelligence layer.

## Implemented

- `SiteInspector`
- `TemplateInspector`
- `TVInspector`
- `ChunkInspector`
- `SnippetInspector`
- `MIGXInspector`
- `SiteIntelligenceService`
- `SchemaService`
- `SiteFingerprintService`
- `Site Contract 1.0`
- deterministic SHA-256 fingerprinting
- bounded resource discovery
- application/API boundary for site schema discovery
- unit tests for fingerprint stability and semantic changes

## Fingerprint invariant

The volatile `generated_at` field is excluded from fingerprint normalization. Consequently, two otherwise identical discovery results must produce the same fingerprint.

## Safety boundary

Discovery is read-only. It does not create, update, publish or delete MODX objects. Only an explicit, bounded resource inventory is exported. Full MODX configuration is not exposed by Site Intelligence.

## Runtime verification required

A real MODX 3.2+ instance must verify:

- all class names and fields against the installed MODX schema;
- xPDO collection queries;
- MIGX detection when MIGX is installed;
- output against a populated MODX site;
- performance on large resource trees;
- authorization before exposing the API method;
- persistence of schema/fingerprint snapshots in the next iteration.

No claim of runtime verification is made until the Docker integration suite is executed.
