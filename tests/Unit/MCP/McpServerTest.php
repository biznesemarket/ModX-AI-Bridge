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

    public function testRegistryResolvesUriTemplates(): void
    {
        $r=new McpRegistry();
        $r->resource('modx://site/schema','Schema','Schema','application/json',fn()=>[],'site.schema');
        $r->resourceTemplate('modx://resource/{id}','Resource','Resource','application/json',fn()=>[],'resource.read');
        self::assertSame('modx://resource/{id}',$r->resourceTemplates()[0]['uriTemplate']);

        $exact=$r->resolveResource('modx://site/schema');
        self::assertSame('site.schema',$exact['resource']['operation']);
        self::assertSame([],$exact['parameters']);

        $template=$r->resolveResource('modx://resource/42');
        self::assertSame('resource.read',$template['resource']['operation']);
        self::assertSame(['id'=>'42'],$template['parameters']);

        self::assertNull($r->resolveResource('modx://resource/42/extra'));
        self::assertNull($r->resolveResource('modx://unknown'));
    }
}
