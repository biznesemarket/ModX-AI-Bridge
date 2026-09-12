<?php

declare(strict_types=1);

namespace AIBridge\Services;

use MODX\Revolution\modX;

final class CacheInvalidationService
{
    public function __construct(private readonly modX $modx) {}

    public function invalidateResource(int $resourceId): array
    {
        try {
            $refreshed = $this->modx->cacheManager->refresh(['db' => []]);
            return ['resource_id' => $resourceId, 'refreshed' => (bool) $refreshed];
        } catch (\Throwable $e) {
            return ['resource_id' => $resourceId, 'refreshed' => false, 'warning' => 'cache_invalidation_failed'];
        }
    }
}
