# Release Artifact

The authoritative distributable is the MODX Transport Package produced by `_build/build.php`.

Release verification must include:

- package filename/signature;
- SHA-256 checksum;
- package contents;
- package version/release;
- generated xPDO model metadata;
- resolver presence;
- migration inventory;
- CI quality-gate result.

A source ZIP is a development snapshot and is not a substitute for the Transport Package used by a production MODX installation.
