# Capability Discovery

The capability manifest is the canonical machine-readable description of what an AI client may request.

It distinguishes:

- available read capabilities;
- validation capabilities;
- guarded execution capabilities;
- disabled-by-default capabilities;
- approval-gated capabilities.

The manifest is descriptive. Actual authorization is always evaluated at execution time against the authenticated principal and current policy.
