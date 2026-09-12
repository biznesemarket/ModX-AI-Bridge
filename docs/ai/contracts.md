# AI-Oriented Contracts

Status: **Review**

AI-facing contracts use explicit versions and separate input/output schemas. A contract describes risk, required validation, security decision, audit behavior and whether idempotency is required.

Example conceptual contract:

```json
{
  "contractVersion": "1.0",
  "operation": "content.validate",
  "risk": "low",
  "execution": {
    "validate": true,
    "securityDecision": true,
    "audit": true,
    "idempotencyRequired": false
  }
}
```

Contracts are intentionally independent from a particular AI vendor or model. OpenAI, Anthropic, local models and custom agents can consume the same machine-readable capability manifest.
