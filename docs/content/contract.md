# Content Contract

Status: **Draft**

## Purpose

The Content Contract is the machine-readable agreement between an AI application and a MODX site's content model.

It prevents an AI agent from treating every MODX resource as an unconstrained HTML blob.

## Version

Current contract version: `1.0`.

The contract is designed to be extended without changing existing field semantics.

## Field model

Each field may contain:

- `type` — semantic value type;
- `required` — whether the value is mandatory;
- `max_length` — hard length constraint where applicable;
- `tv_id` — originating MODX TV identifier;
- `caption` — human-facing label.

## Conservative discovery

The bridge may discover the existence and configuration of a TV but must not infer mandatory status from its existence alone.

Template-to-TV relation discovery is a separate concern and is intentionally not approximated in this iteration.

## SEO model

The default resource contract describes recommended boundaries for:

- title;
- meta description;
- exactly one H1.

These are QA rules, not a claim that every MODX installation uses the same SEO implementation.
