# Profiles

Profile lifecycle: `active → disabled → retired`. Disabled and retired profiles reject authenticated operations.

Required fields: `name`, `site_key`, `environment`. Optional fields: `base_url`, `metadata`.

Security invariant: a principal authenticated against profile A must not read or mutate profile B.
