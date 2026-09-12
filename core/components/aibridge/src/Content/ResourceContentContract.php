<?php

declare(strict_types=1);

namespace AIBridge\Content;

use AIBridge\Contracts\ContentContract;

final class ResourceContentContract implements ContentContract
{
    public function __construct(private readonly array $data)
    {
    }

    public function type(): string
    {
        return 'resource';
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
