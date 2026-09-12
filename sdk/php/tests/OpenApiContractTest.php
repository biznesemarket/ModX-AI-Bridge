<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class OpenApiContractTest extends TestCase
{
    private static function spec(): string
    {
        $path = dirname(__DIR__, 3) . '/docs/api/openapi.yaml';
        self::assertFileExists($path);
        return (string) file_get_contents($path);
    }

    public function testEverySdkPathIsDocumentedInOpenApi(): void
    {
        $spec = self::spec();
        $paths = [
            '/api/ai/v2/capabilities',
            '/api/ai/v2/profiles',
            '/api/ai/v2/site/schema',
            '/api/ai/v2/site/fingerprint',
            '/api/ai/v2/content/contract',
            '/api/ai/v2/content/validate',
            '/api/ai/v2/resources',
            '/api/ai/v2/resources/preview',
            '/api/ai/v2/resources/{id}',
            '/api/ai/v2/resources/{id}/publish',
            '/api/ai/v2/jobs/{id}',
            '/api/ai/v2/mcp',
            '/api/ai/v2/health',
            '/api/ai/v2/ready',
        ];
        foreach ($paths as $path) {
            self::assertStringContainsString($path . ':', $spec, "OpenAPI is missing {$path}");
        }
    }

    public function testMutationIdempotencyHeaderIsDocumented(): void
    {
        $spec = self::spec();
        self::assertGreaterThanOrEqual(4, substr_count($spec, 'Idempotency-Key'), 'Each mutation must document the Idempotency-Key header.');
    }

    public function testMutationsDocumentQueuedResponse(): void
    {
        self::assertStringContainsString("'202': {description: Job queued}", self::spec());
    }
}
