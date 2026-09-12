<?php
declare(strict_types=1);
namespace AIBridge\Tests\Unit\MCP;
use AIBridge\MCP\McpRegistry;
use PHPUnit\Framework\TestCase;
final class McpServerTest extends TestCase
{
    public function testRegistryExposesMachineReadableDefinitions(): void
    {
        $r=new McpRegistry();
        $r->tool('x','X',['type'=>'object'],fn()=>[],'site.schema');
        $r->resource('modx://x','X','X','text/plain',fn()=>[],'site.schema');
        $r->prompt('p','P',[],fn()=>[]);
        self::assertSame('x',$r->tools()[0]['name']);
        self::assertSame('modx://x',$r->resources()[0]['uri']);
        self::assertSame('p',$r->prompts()[0]['name']);
    }
}
