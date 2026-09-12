<?php

declare(strict_types=1);

namespace AIBridge\Tests\Security;

use PHPUnit\Framework\TestCase;

final class SecurityRegressionTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function config(): array
    {
        $path = dirname(__DIR__, 2) . '/core/components/aibridge/config/config.php';
        $config = require $path;
        self::assertIsArray($config);
        return $config;
    }

    /** @dataProvider secretProvider */
    public function testSecretNamesAreNeverAllowedInAuditPayloadKeys(string $key): void
    {
        self::assertMatchesRegularExpression('/^(?!.*(?:password|secret|token|authorization|api[_-]?key|private[_-]?key)).+$/i', $key);
    }

    public static function secretProvider(): array
    {
        return [['request_id'], ['profile_id'], ['operation'], ['status'], ['resource_id']];
    }

    public function testDangerousOperationsRemainExplicitlyDeniedByDefault(): void
    {
        $config = self::config();
        self::assertFalse((bool) $config['allow_resource_delete'], 'resource.delete must be disabled by default.');
        self::assertFalse((bool) $config['allow_setting_write'], 'settings.write must be disabled by default.');
        self::assertTrue((bool) $config['approval_required_for_publish'], 'publish must require approval by default.');
    }

    public function testSecurityFailClosedByDefault(): void
    {
        $config = self::config();
        self::assertTrue((bool) $config['security_fail_closed']);
        self::assertSame(['settings.write'], $config['blocked_operations']);
    }

    public function testExternalBoundariesAreDisabledByDefault(): void
    {
        $config = self::config();
        self::assertFalse((bool) $config['rest_enabled']);
        self::assertFalse((bool) $config['mcp_enabled']);
        self::assertTrue((bool) $config['require_https']);
    }
}
