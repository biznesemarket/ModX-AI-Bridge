# Release Candidate Policy

The current project is treated as a release candidate, not a Stable release.

Recommended release identity for the first runtime-certified build:

`0.1.0-rc1`

Stable release `0.1.0` may only be created after the Final Integration & Stabilization gate is green.

The Transport Package must be rebuilt from the exact Git commit that passed all gates. The resulting archive must be hashed and retained with the release metadata.
