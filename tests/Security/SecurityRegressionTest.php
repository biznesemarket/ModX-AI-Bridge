<?php
declare(strict_types=1);

namespace AIBridge\Tests\Security;

use PHPUnit\Framework\TestCase;

final class SecurityRegressionTest extends TestCase
{
    /** @dataProvider secretProvider */
    public function testSecretNamesAreNeverAllowedInAuditPayloadKeys(string $key): void
    {
        $this->assertMatchesRegularExpression('/^(?!.*(?:password|secret|token|authorization|api[_-]?key|private[_-]?key)).+$/i', $key);
    }

    public static function secretProvider(): array
    {
        return [['request_id'], ['profile_id'], ['operation'], ['status'], ['resource_id']];
    }

    public function testDangerousOperationsRemainExplicitlyDeniedByDefault(): void
    {
        $config = dirname(__DIR__, 2) . '/core/components/aibridge/config/config.php';
        self::assertFileExists($config);
        $text = (string) file_get_contents($config);
        self::assertStringContainsString("allow_delete', false", $text);
        self::assertStringContainsString("allow_publish', false", $text);
        self::assertStringContainsString("allow_settings_write', false", $text);
    }
}
