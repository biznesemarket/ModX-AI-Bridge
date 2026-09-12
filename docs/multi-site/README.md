# Multi-Site & Bridge Profiles

Status: Review

A Bridge Profile is the security and context boundary for one MODX site. Tokens, jobs, schemas, fingerprints, policies, snapshots, idempotency records and audit records carry `profile_id`.

## Isolation

Every request must resolve exactly one active profile before a site-scoped operation executes. A token is bound to one profile; a token cannot select another profile through request payload.

## Site key

`site_key` is a stable opaque identifier. Do not use a URL as the security identity. `base_url` is metadata only.

## Orchestrator model

AI Orchestrator → profile-specific token → Bridge → profile-scoped Application Services → MODX.

Cross-profile aggregation belongs in an external orchestrator and must never be implemented by granting a token implicit access to all profiles.
