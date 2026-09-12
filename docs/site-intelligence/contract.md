# Site Contract 1.0

**Status:** Draft

`modx-ai-bridge/site@1.0` is a machine-readable snapshot of the site implementation relevant to AI-assisted MODX operations.

Top-level fields:

- `contract`
- `version`
- `generated_at`
- `site`
- `templates`
- `template_variables`
- `chunks`
- `snippets`
- `migx`
- `resources`

IDs are represented as integers. Human-readable names remain strings. Optional MODX values may be `null`.

The contract is descriptive rather than prescriptive: it reports what exists. Content Contract and Policy layers determine what an agent may do with the discovered structure.
