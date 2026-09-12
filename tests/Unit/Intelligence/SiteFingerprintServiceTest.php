<?php

declare(strict_types=1);

use AIBridge\Services\SiteFingerprintService;
use PHPUnit\Framework\TestCase;

final class SiteFingerprintServiceTest extends TestCase
{
    public function testGeneratedTimestampDoesNotChangeFingerprint(): void
    {
        $service = new SiteFingerprintService();
        $a = $service->fingerprint(['generated_at' => '2026-01-01T00:00:00Z', 'templates' => [['id' => 1]]]);
        $b = $service->fingerprint(['generated_at' => '2026-02-01T00:00:00Z', 'templates' => [['id' => 1]]]);
        self::assertSame($a['fingerprint'], $b['fingerprint']);
    }

    public function testSemanticChangeChangesFingerprint(): void
    {
        $service = new SiteFingerprintService();
        $a = $service->fingerprint(['templates' => [['id' => 1, 'name' => 'base']]]);
        $b = $service->fingerprint(['templates' => [['id' => 1, 'name' => 'catalog']]]);
        self::assertNotSame($a['fingerprint'], $b['fingerprint']);
    }
}
