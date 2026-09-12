# Resource Execution Engine

Iteration 11 introduces the controlled execution path for MODX resource mutations.

Supported operations:

- `resource.create`
- `resource.update`
- `resource.delete`
- `resource.preview`
- `resource.publish`

The execution layer is application-level and is not coupled to REST or MCP. Both protocols call the same application services.
