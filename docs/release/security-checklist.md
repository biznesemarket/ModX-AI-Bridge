# Production Security Checklist

- [ ] HTTPS enforced.
- [ ] Bearer token hashes only; plaintext tokens are never persisted.
- [ ] Least-privilege scopes verified.
- [ ] Profile isolation verified.
- [ ] IP allowlist reviewed.
- [ ] Rate limiting enabled.
- [ ] Idempotency required for mutations.
- [ ] Delete disabled unless explicitly approved by policy.
- [ ] Publish requires human approval where policy requires it.
- [ ] Manager permission boundary enabled.
- [ ] Secrets redacted from logs, audit and API errors.
- [ ] Audit retention configured.
- [ ] Database backup tested.
- [ ] Restore procedure tested.
- [ ] Snapshot rollback tested.
- [ ] Worker credentials use least privilege.
- [ ] Production debug mode disabled.
