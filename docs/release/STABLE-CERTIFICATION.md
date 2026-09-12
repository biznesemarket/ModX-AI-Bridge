# Stable Certification

## Certification pipeline

```text
Source
  -> Static
  -> Unit
  -> Contract
  -> Security
  -> MODX Runtime
  -> E2E
  -> Migration
  -> Package
  -> Artifact Integrity
  -> Production Checklist
  -> STABLE
```

Certification evidence is stored as CI artifacts. A failed or unavailable gate blocks certification.

Required evidence:

- exact commit/tag;
- MODX version tested;
- PHP version;
- database engine/version;
- test report;
- security report;
- Transport Package checksum;
- migration result;
- rollback result.
