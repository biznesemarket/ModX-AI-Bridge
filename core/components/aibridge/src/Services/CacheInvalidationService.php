<?php

declare(strict_types=1);

namespace AIBridge\Services;

use MODX\Revolution\modContext;
use MODX\Revolution\modResource;
use MODX\Revolution\modX;

final class CacheInvalidationService
{
    public function __construct(private readonly modX $modx) {}

    /**
     * Drop the cached representation of a single resource and the cached
     * context map it belongs to.
     *
     * A previous implementation refreshed the whole MODX `db` cache partition,
     * which either is a no-op (when `cache_db` is disabled) or clears it for
     * every context. Invalidation is scoped instead: the resource page cache is
     * deleted and the owning context's cached `resourceMap`/`aliasMap` entry is
     * removed, so it is regenerated lazily on the next request instead of an
     * eager whole-context rebuild on the mutation path. The resource object must
     * be supplied by the caller so deletes still know the owning context after
     * the row is removed.
     */
    public function invalidateResource(int $resourceId, ?modResource $resource = null): array
    {
        try {
            if (!$resource instanceof modResource) {
                return ['resource_id' => $resourceId, 'refreshed' => false, 'warning' => 'resource_not_found'];
            }
            $contextKey = (string) $resource->get('context_key');
            $resource->clearCache($contextKey);
            $contextCacheDeleted = false;
            if ($contextKey !== '') {
                $context = $this->modx->getObject(modContext::class, $contextKey);
                if ($context instanceof modContext) {
                    $contextCacheDeleted = (bool) $this->modx->cacheManager->delete($context->getCacheKey(), [
                        \xPDO\xPDO::OPT_CACHE_KEY => $this->modx->getOption('cache_context_settings_key', null, 'context_settings'),
                    ]);
                }
            }
            return ['resource_id' => $resourceId, 'context' => $contextKey, 'refreshed' => true, 'context_cache_deleted' => $contextCacheDeleted];
        } catch (\Throwable $e) {
            return ['resource_id' => $resourceId, 'refreshed' => false, 'warning' => 'cache_invalidation_failed'];
        }
    }
}
