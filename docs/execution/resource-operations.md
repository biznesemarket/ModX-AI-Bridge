# Resource Operations

## Create

Creates a `modResource` using a strict whitelist of resource fields. `template` and `pagetitle` are validated through the Content Contract. TV values can be supplied through the `tvs` object.

## Update

Loads the current resource, merges the proposed fields with the current representation, validates the resulting state, snapshots the previous state and then saves the resource.

## Delete

Requires the `resource:delete` scope and the `aibridge_allow_resource_delete` policy switch. A snapshot is created before deletion. The default policy is deny.

## Preview

Returns a non-persistent content-level preview and canonical resource URL. The safe preview does not execute snippets or persist changes.

## Publish

Requires `resource:publish` and, by default, an `approval_id`. Publication is performed only after the same security and Content QA gates used by other mutations.
