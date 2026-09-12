# Release Candidate Policy

This document records the release-candidate policy used before the first Stable release.

The first runtime-certified build was released as `0.1.0-rc1`.

Stable release `0.1.0` may only be created after the Final Integration & Stabilization gate is green. That
condition was met in Iterations 40–41: `AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh`
printed `STABLE certification gates passed.` on commit `160a659` (run `34716441523`), so Stable `0.1.0`
(tag `v0.1.0`) supersedes the Release Candidate. See `docs/release/0.1.0.md`.

The Transport Package must be rebuilt from the exact Git commit that passed all gates. The resulting archive
must be hashed and retained with the release metadata (transport archives are not byte-reproducible because
they embed file timestamps, so every build records its own SHA-256).
