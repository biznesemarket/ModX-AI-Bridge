<?php

declare(strict_types=1);

namespace AIBridge\Tests\Integration;

use AIBridge\Security\IdempotencyService;
use AIBridge\Security\RateLimiter;
use AIBridge\Services\CacheInvalidationService;
use PHPUnit\Framework\TestCase;

/**
 * Defect #37: idempotency keys and rate-limit buckets must be scoped by
 * `profile_id`, both in their unique indexes and in every lookup.
 *
 * Defect #41: cache invalidation must target one resource/context instead of
 * flushing the whole MODX `db` cache partition.
 */
final class ProfileScopedStorageTest extends TestCase
{
    private static \MODX\Revolution\modX $modx;

    public static function setUpBeforeClass(): void
    {
        $root = getenv('MODX_ROOT');
        if (!$root) {
            self::markTestSkipped('MODX_ROOT is not configured.');
        }

        $root = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        require_once $root . 'config.core.php';
        require_once MODX_CORE_PATH . 'vendor/autoload.php';

        self::$modx = new \MODX\Revolution\modX();
        self::$modx->initialize('mgr');

        $namespace = self::$modx->getObject(\MODX\Revolution\modNamespace::class, ['name' => 'aibridge']);
        if (!$namespace) {
            self::markTestSkipped('AIBridge Extra is not installed.');
        }
        $namespacePath = \MODX\Revolution\modNamespace::translatePath(self::$modx, (string) $namespace->get('path'));
        require_once rtrim((string) $namespacePath, '/') . '/bootstrap.php';
    }

    public function testRateLimitBucketsAreProfileScoped(): void
    {
        $key = 'rate-profile-' . bin2hex(random_bytes(6));
        $hash = hash('sha256', $key);
        $limiter = new RateLimiter(self::$modx);

        try {
            $first = $limiter->consume($key, 5, 60, 900001);
            $other = $limiter->consume($key, 5, 60, 900002);
            self::assertTrue($first['allowed']);
            self::assertTrue($other['allowed']);
            self::assertSame(4, $first['remaining']);
            self::assertSame(4, $other['remaining'], 'A second profile must not consume the first profile bucket.');

            $again = $limiter->consume($key, 5, 60, 900001);
            self::assertSame(3, $again['remaining']);
        } finally {
            self::$modx->removeCollection(\AIBridge\Model\RateLimitBucket::class, ['bucket_key' => $hash]);
        }
    }

    public function testIdempotencyKeysAreProfileScoped(): void
    {
        $key = 'idem-profile-' . bin2hex(random_bytes(6));
        $principal = 'principal-' . bin2hex(random_bytes(4));
        $operation = 'resource.update';
        $hash = hash('sha256', '{"id":1}');
        $service = new IdempotencyService(self::$modx);

        try {
            $first = $service->begin($key, $principal, $operation, $hash, 900001);
            self::assertTrue($first['accepted']);

            $replay = $service->begin($key, $principal, $operation, $hash, 900001);
            self::assertTrue($replay['replay'] ?? false);

            $other = $service->begin($key, $principal, $operation, $hash, 900002);
            self::assertTrue($other['accepted'] ?? false, 'The same key under another profile must be independent.');
            self::assertFalse($other['replay'] ?? false);

            $conflict = $service->begin($key, $principal, $operation, hash('sha256', '{"id":2}'), 900001);
            self::assertTrue($conflict['conflict'] ?? false);
        } finally {
            self::$modx->removeCollection(\AIBridge\Model\IdempotencyKey::class, ['idempotency_key' => $key]);
        }
    }

    public function testAbandonedInProgressKeyCanBeReusedButCompletedKeyCannot(): void
    {
        $key = 'idem-abandon-' . bin2hex(random_bytes(6));
        $principal = 'principal-' . bin2hex(random_bytes(4));
        $operation = 'resource.update';
        $hash = hash('sha256', '{"id":1}');
        $service = new IdempotencyService(self::$modx);

        try {
            self::assertTrue($service->begin($key, $principal, $operation, $hash, 900001)['accepted']);
            $service->abandon($key, $principal, $operation, 900001);
            self::assertTrue($service->begin($key, $principal, $operation, $hash, 900001)['accepted'], 'An abandoned in-progress key must be reusable.');

            $service->complete($key, $principal, $operation, ['ok' => true], 900001);
            $service->abandon($key, $principal, $operation, 900001);
            $replay = $service->begin($key, $principal, $operation, $hash, 900001);
            self::assertTrue($replay['replay'] ?? false, 'A completed key must never be abandoned.');
        } finally {
            self::$modx->removeCollection(\AIBridge\Model\IdempotencyKey::class, ['idempotency_key' => $key]);
        }
    }

    public function testCacheInvalidationIsScopedToTheResourceContext(): void
    {
        $service = new CacheInvalidationService(self::$modx);

        $missing = $service->invalidateResource(0);
        self::assertFalse($missing['refreshed']);
        self::assertSame('resource_not_found', $missing['warning'] ?? null);

        $resource = self::$modx->newObject(\MODX\Revolution\modResource::class);
        $resource->fromArray([
            'id' => 987654321,
            'context_key' => 'web',
            'alias' => 'aibridge-cache-scope',
            'pagetitle' => 'aibridge cache scope',
        ]);

        $result = $service->invalidateResource((int) $resource->get('id'), $resource);
        self::assertSame('web', $result['context'] ?? null);
        self::assertTrue((bool) ($result['refreshed'] ?? false));
        self::assertArrayNotHasKey('warning', $result);
    }
}
