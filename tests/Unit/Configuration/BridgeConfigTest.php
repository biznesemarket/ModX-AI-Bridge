<?php

declare(strict_types=1);

use AIBridge\Configuration\BridgeConfig;
use PHPUnit\Framework\TestCase;

final class BridgeConfigTest extends TestCase
{
    public function testReadsValuesAndDefaults(): void
    {
        $config = new BridgeConfig(['rest_enabled' => false, 'rate_limit_per_minute' => 60]);

        self::assertFalse($config->isEnabled('rest_enabled'));
        self::assertSame(60, $config->get('rate_limit_per_minute'));
        self::assertSame('fallback', $config->get('missing', 'fallback'));
    }
}
