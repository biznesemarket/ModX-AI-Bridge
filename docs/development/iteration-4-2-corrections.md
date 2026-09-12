# Iteration 4.2 Corrections

Status: Review

A second review against the current MODX 3 documentation identified an important packaging correction.

## Build convention

Current ModExtra3 documentation uses:

```text
_build/build.php
_build/config.inc.php
_build/elements/
_build/resolvers/
```

The earlier `build.config.php` was therefore removed from the authoritative build path.

## Manager menu

MODX 3 uses `modMenu` with a string `action`. New code must not introduce `modAction`.

The declarative menu definition is now:

```php
return [
    'aibridge' => [
        'description' => 'aibridge.menu_desc',
        'action' => 'home',
        'parent' => 'components',
    ],
];
```

## Runtime status

This correction does not claim that the package has been executed against a real MODX 3 installation inside the current environment. That remains an external integration gate.

## References

- MODX 3 server requirements
- MODX 3 package build / current modExtra scaffold
- MODX 3 custom manager pages
