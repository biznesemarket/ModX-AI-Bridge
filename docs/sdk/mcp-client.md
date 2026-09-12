# MCP Client Integration

MCP clients use the Bridge MCP endpoint rather than accessing MODX directly. The integration supports capability discovery and the standard Bridge methods `initialize`, `tools/list`, `tools/call`, `resources/list`, `resources/read`, `prompts/list`, and `prompts/get`.

The same bearer token and server-side policy boundary apply. A client must not treat a listed tool as permission to execute it; authorization is evaluated for every call.
