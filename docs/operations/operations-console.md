# Operations Console

Status: Draft

The MODX Manager Operations Console is the administrative UI for ModX AI Bridge.

## Scope

The console exposes operational state for:

- profiles;
- tokens without plaintext secrets;
- policies;
- jobs and progress;
- audit events;
- site fingerprints;
- health/readiness;
- system/runtime information.

## Security boundary

All manager processors require an authenticated MODX Manager session and the explicit `aibridge_manage` permission. The UI never becomes an alternative API authentication mechanism.

Token hashes and plaintext credentials are never returned to the browser. Revocation/disable operations are fail-closed and server-side validated.

## Actions

The initial console supports safe status operations:

- profile: `active`, `disabled`, `retired`;
- token: `active`, `disabled`, `revoked`;
- policy: `active`, `disabled`.

Creation, rotation and policy authoring remain explicit server-side workflows until their audit and approval semantics are finalized.
