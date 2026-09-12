<?php
declare(strict_types=1);
namespace AIBridge\Tests\Unit\Verification;
use AIBridge\Verification\ReleaseGate;
use PHPUnit\Framework\TestCase;
final class ReleaseGateTest extends TestCase { public function testPass():void{$r=(new ReleaseGate())->evaluate(['syntax'=>true,'contracts'=>['ok'=>true]]);$this->assertTrue($r['passed']);}public function testFailClosed():void{$r=(new ReleaseGate())->evaluate(['runtime'=>['ok'=>false]]);$this->assertFalse($r['passed']);$this->assertSame(['runtime'],$r['failed']);}}
