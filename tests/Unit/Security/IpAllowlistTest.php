<?php

declare(strict_types=1);
namespace AIBridge\Tests\Unit\Security;
use AIBridge\Security\IpAllowlist;
use PHPUnit\Framework\TestCase;
final class IpAllowlistTest extends TestCase
{
    public function testExactAndCidrRules(): void
    {
        $service=new IpAllowlist();
        $this->assertTrue($service->allows('10.0.0.12',['10.0.0.12']));
        $this->assertTrue($service->allows('10.0.0.12',['10.0.0.0/24']));
        $this->assertFalse($service->allows('10.0.1.12',['10.0.0.0/24']));
    }
}
