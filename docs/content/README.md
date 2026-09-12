# Content Contract & Content QA

Status: **Draft**

Iteration 7 introduces the first implementation of the content governance layer. It separates two concepts:

1. **Content Contract** — what structure and constraints apply to a resource.
2. **Content QA** — whether a proposed payload satisfies those constraints.

The layer is intentionally conservative. Site discovery alone does not prove that a field is mandatory, that a JSON-LD type is appropriate, or that a particular TV is required. Therefore only `pagetitle` is mandatory by default; additional requirements must come from explicit policy or future site-specific configuration.

## Contract

A resource contract describes:

- MODX resource fields;
- discovered TVs;
- template identity when known;
- SEO title and description rules;
- H1 cardinality;
- HTML restrictions;
- JSON-LD syntax validation.

## QA

Validation returns machine-readable `errors`, `warnings` and `metrics`.

Errors block an operation. Warnings do not block it but must remain visible to the calling agent/application.

## Design boundary

Content QA does not publish or save a resource. It is a pure validation layer. The execution pipeline will later use:

`request → contract → validate → policy → preview → approval → commit`

No AI-generated content is treated as trusted merely because an LLM produced it.
