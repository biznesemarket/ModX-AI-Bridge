<?php
declare(strict_types=1);
namespace AIBridge\Tests\Unit\Verification;
use AIBridge\Verification\VerificationResult;
use PHPUnit\Framework\TestCase;
final class VerificationResultTest extends TestCase { public function testSerialization():void{$r=(new VerificationResult(true,['a'=>1],['a'=>1]))->toArray();$this->assertTrue($r['valid']);$this->assertSame([],$r['mismatches']);}}
