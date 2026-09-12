# Manager Security

Status: Draft

The operations console follows these rules:

- require an authenticated `mgr` session;
- require the dedicated `aibridge_manage` permission;
- fail closed if permission is absent;
- never expose token plaintext or token hashes;
- validate entity, identifier and status server-side;
- perform mutations only through Manager processors;
- retain existing audit and profile isolation requirements;
- do not treat browser-side hiding as authorization.
