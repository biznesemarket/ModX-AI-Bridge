<?php

declare(strict_types=1);

namespace AIBridge\Configuration;

final class BridgeConfig
{
    public function __construct(private readonly array $values)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function isEnabled(string $key): bool
    {
        return (bool) $this->get($key, false);
    }

    public function all(): array
    {
        return $this->values;
    }
}
