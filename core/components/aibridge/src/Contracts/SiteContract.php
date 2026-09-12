<?php

declare(strict_types=1);

namespace AIBridge\Contracts;

interface SiteContract
{
    public function version(): string;

    public function toArray(): array;

    public function hash(): string;
}
