# Secrets Management

Never commit:

- API bearer tokens;
- token hashes generated from production credentials when they can be correlated with secrets;
- database passwords;
- MODX admin passwords;
- signing/private keys;
- external AI provider credentials.

Preferred sources, in order:

1. secret manager;
2. container/orchestrator secret store;
3. protected environment variables;
4. protected deployment configuration outside Git.

Logs must apply the existing Bridge secret-redaction rules before serialization.
