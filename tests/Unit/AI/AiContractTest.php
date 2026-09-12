<?php
declare(strict_types=1);
namespace AIBridge\Tests\Unit\AI;
use AIBridge\AI\AiContract;
use PHPUnit\Framework\TestCase;
final class AiContractTest extends TestCase
{
    public function testRiskControlsIdempotency(): void
    {
        $c=AiContract::operation('resource.write',['type'=>'object'],['type'=>'object'],'high');
        self::assertSame('1.0',$c['contractVersion']); self::assertTrue($c['execution']['idempotencyRequired']);
    }
}
