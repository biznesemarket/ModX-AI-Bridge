<?php

declare(strict_types=1);

namespace AIBridge\Contracts;

final class DiscoveredSite implements SiteContract
{
    public function __construct(
        private readonly string $version,
        private readonly array $data
    ) {
    }

    public function version(): string
    {
        return $this->version;
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function hash(): string
    {
        $json = json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return hash('sha256', $json);
    }
}
