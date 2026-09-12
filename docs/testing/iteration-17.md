# Iteration 17 Testing

Status: Review

Static verification covers the Manager processor namespace, console service and JavaScript/CSS assets.

Required runtime checks before Stable:

1. Open the component from MODX Manager.
2. Verify an authenticated Manager without `aibridge_manage` receives a denial.
3. Verify an authorized Manager can read the console.
4. Verify token hashes and plaintext tokens never appear in the response or browser DOM.
5. Verify profile/token/policy status mutations are rejected for invalid identifiers/statuses.
6. Verify audit events remain profile-scoped.
7. Verify readiness changes when the database or Bridge namespace is unavailable.
