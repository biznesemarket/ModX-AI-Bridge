<?php

declare(strict_types=1);

namespace AIBridge\Processors;

final class ResourceValidateProcessor
{
    public function process(array $payload = []): array
    {
        // Boundary only. Business logic belongs in application/domain services.
        return [
            'processor' => 'ResourceValidateProcessor',
            'status' => 'application-boundary',
            'payload' => $payload,
        ];
    }
}
