<?php

declare(strict_types=1);

namespace AIBridge\Contracts;

interface Policy
{
    public function allows(string $operation, array $context = []): bool;
}
