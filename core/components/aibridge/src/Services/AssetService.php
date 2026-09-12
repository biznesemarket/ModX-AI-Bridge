<?php

declare(strict_types=1);

namespace AIBridge\Services;

final class AssetService
{
    public function inspect(array $input = []): array
    {
        return [
            'service' => 'AssetService',
            'status' => 'stub',
            'input' => $input,
        ];
    }
}
