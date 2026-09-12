<?php

declare(strict_types=1);

namespace AIBridge\Contracts;

interface ContentContract
{
    public function type(): string;

    public function toArray(): array;
}
