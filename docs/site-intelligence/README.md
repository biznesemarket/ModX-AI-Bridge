# Site Intelligence

**Status:** Draft

Site Intelligence is the discovery layer of ModX AI Bridge. Its purpose is to expose the actual MODX site model to an AI agent without exposing secrets or requiring the agent to infer the site's implementation.

## Discovery contract

The first contract version is `modx-ai-bridge/site@1.0` and contains:

- site identity and routing configuration;
- templates;
- template variables (TVs);
- chunks;
- snippets;
- MIGX detection metadata;
- a bounded resource inventory.

The discovery result also includes a SHA-256 fingerprint. `generated_at` is deliberately excluded from fingerprint normalization so repeated discovery of an unchanged site produces the same fingerprint.

## Safety boundary

Discovery is read-only. It does not create, update, publish or delete MODX objects. Secrets and full MODX configuration are not exported. Resource discovery is bounded by a maximum limit and can be scoped by `root_id`.

## Why this exists

An AI agent must know the site's real implementation before proposing or executing a content operation. Site Intelligence is therefore upstream of Content Contract validation and execution.

## Next work

Future iterations will add:

1. template-to-TV bindings;
2. resource-to-template distribution;
3. context topology;
4. dependency graph extraction;
5. schema version persistence;
6. incremental change detection;
7. explicit Site Contract compatibility rules.
