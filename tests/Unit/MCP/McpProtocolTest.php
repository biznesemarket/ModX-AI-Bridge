<?php
declare(strict_types=1);
namespace AIBridge\Tests\Unit\MCP;
use AIBridge\MCP\McpProtocol;
use PHPUnit\Framework\TestCase;
final class McpProtocolTest extends TestCase
{
    public function testProtocolUsesJsonRpcShape(): void
    {
        $p=new McpProtocol(); $r=$p->result(1,['ok'=>true]);
        self::assertSame('2.0',$r['jsonrpc']); self::assertSame(1,$r['id']); self::assertTrue($r['result']['ok']);
    }
}
