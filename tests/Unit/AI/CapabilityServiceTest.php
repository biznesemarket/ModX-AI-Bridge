<?php
declare(strict_types=1);
namespace AIBridge\Tests\Unit\AI;
use AIBridge\AI\CapabilityService;
use PHPUnit\Framework\TestCase;
final class CapabilityServiceTest extends TestCase
{
    public function testManifestContainsSafetyStates(): void
    {
        $manifest=(new CapabilityService())->manifest();
        $statuses=array_column($manifest['capabilities'],'status');
        self::assertContains('available',$statuses);
        self::assertContains('disabled-by-default',$statuses);
        self::assertContains('approval-gated',$statuses);
    }
}
