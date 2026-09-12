<?php

declare(strict_types=1);

namespace AIBridge\Processors;

final class ResourcePreviewProcessor
{
    public function process(array $payload = []): array
    {
        // Boundary only. Business logic belongs in application/domain services.
        return [
            'processor' => 'ResourcePreviewProcessor',
            'status' => 'application-boundary',
            'payload' => $payload,
        ];
    }
}
