<?php
declare(strict_types=1);

namespace AIBridge\Tests\Contract;

use PHPUnit\Framework\TestCase;

final class McpContractTest extends TestCase
{
    public function testMcpContractDocumentsCapabilities(): void
    {
        $file = dirname(__DIR__, 2) . '/docs/mcp/tools.md';
        self::assertFileExists($file);
        $text = (string) file_get_contents($file);
        self::assertStringContainsString('tools/list', $text);
        self::assertStringContainsString('tools/call', $text);
        self::assertStringContainsString('content_validate', $text);
    }
}
