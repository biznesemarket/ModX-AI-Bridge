<?php

declare(strict_types=1);

namespace AIBridge\Processors;

final class ResourcePublishProcessor
{
    public function process(array $payload = []): array
    {
        // Boundary only. Business logic belongs in application/domain services.
        return [
            'processor' => 'ResourcePublishProcessor',
            'status' => 'application-boundary',
            'payload' => $payload,
        ];
    }
}
