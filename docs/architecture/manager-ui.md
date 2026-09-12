# Manager UI Architecture

Status: Draft

```text
MODX Manager
    |
    v
Operations Console
    |
    +--> Manager Processors
             |
             v
      OperationsConsoleService
             |
       +-----+-----+-----+-----+-----+
       v           v           v       v
    Profiles     Tokens      Jobs     Audit
       |           |           |       |
       +-----------+-----------+-------+
                       |
                       v
                    MODX/xPDO
```

The Manager UI is intentionally separate from the external REST/MCP surface. Manager authentication comes from MODX Manager, while external AI traffic continues through the existing API security pipeline.
