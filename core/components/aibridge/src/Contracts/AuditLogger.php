<?php

declare(strict_types=1);

namespace AIBridge\Contracts;

interface AuditLogger
{
    public function record(string $event, array $context = []): void;
}
