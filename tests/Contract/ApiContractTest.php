<?php
declare(strict_types=1);

namespace AIBridge\Tests\Contract;

use PHPUnit\Framework\TestCase;

final class ApiContractTest extends TestCase
{
    public function testApiVersionAndCoreRoutesAreDocumented(): void
    {
        $spec = dirname(__DIR__, 2) . '/docs/api/openapi.yaml';
        self::assertFileExists($spec);
        $yaml = (string) file_get_contents($spec);
        self::assertStringContainsString('/api/ai/v2', $yaml);
        self::assertStringContainsString('bearerAuth', $yaml);
        self::assertStringContainsString('site/schema', $yaml);
        self::assertStringContainsString('content/validate', $yaml);
    }
}
