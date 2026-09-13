<?php

declare(strict_types=1);

namespace AIBridge\Tests\Integration;

use AIBridge\Api\RestApi;
use AIBridge\Configuration\ConfigFactory;
use AIBridge\MultiSite\ProfileService;
use AIBridge\Security\TokenManager;
use PHPUnit\Framework\TestCase;

final class McpTransportTest extends TestCase
{
    private static ?\MODX\Revolution\modX $modx = null;
    private static string $token = '';

    public static function setUpBeforeClass(): void
    {
        $root = getenv('MODX_ROOT');
        if (!$root) {
            self::markTestSkipped('MODX_ROOT is not configured.');
        }

        $root = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        require_once $root . 'config.core.php';
        require_once MODX_CORE_PATH . 'vendor/autoload.php';

        self::$modx = new \MODX\Revolution\modX();
        self::$modx->initialize('mgr');

        $namespace = self::$modx->getObject(\MODX\Revolution\modNamespace::class, ['name' => 'aibridge']);
        if (!$namespace) {
            self::markTestSkipped('AIBridge Extra is not installed.');
        }
        $namespacePath = \MODX\Revolution\modNamespace::translatePath(self::$modx, (string) $namespace->get('path'));
        require_once rtrim((string) $namespacePath, '/') . '/bootstrap.php';

        self::setting('aibridge_rest_enabled', '1');
        self::setting('aibridge_mcp_enabled', '1');
        self::setting('aibridge_ip_allowlist', '["127.0.0.1"]');
        self::setting('aibridge_require_https', '0');
        self::setting('aibridge_rate_limit_per_minute', '1000');

        $suffix = bin2hex(random_bytes(4));
        $profileId = (int) ((new ProfileService(self::$modx))->create([
            'name' => 'McpTransportTest ' . $suffix,
            'site_key' => 'mcp-transport-test-' . $suffix,
        ])->toArray()['id'] ?? 0);

        self::$token = (string) (new TokenManager(self::$modx, ConfigFactory::fromModx(self::$modx)))
            ->issue('mcp-test-' . $suffix, ['site:read', 'content:validate'], $profileId)['token'];
    }

    public function testInitialize(): void
    {
        $response = $this->mcp(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['clientInfo' => ['name' => 'phpunit']]]);
        self::assertSame(200, $response['status']);
        self::assertSame('2.0', $response['body']['jsonrpc'] ?? null);
        self::assertNotEmpty($response['body']['result']['protocolVersion'] ?? null);
    }

    public function testToolsListContainsExecutionTools(): void
    {
        $response = $this->mcp(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list']);
        $names = array_column($response['body']['result']['tools'] ?? [], 'name');
        foreach (['site_schema', 'content_validate', 'resource_read', 'resource_create', 'resource_update', 'resource_delete', 'resource_publish'] as $tool) {
            self::assertContains($tool, $names);
        }
    }

    public function testToolCallResourceReadRequiresScope(): void
    {
        $response = $this->mcp(['jsonrpc' => '2.0', 'id' => 7, 'method' => 'tools/call', 'params' => ['name' => 'resource_read', 'arguments' => ['id' => 1]]]);
        self::assertSame(200, $response['status']);
        self::assertSame(-32003, $response['body']['error']['code'] ?? null);
    }

    public function testToolCallSiteSchema(): void
    {
        $response = $this->mcp(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'site_schema', 'arguments' => ['limit' => 5]]]);
        self::assertSame(200, $response['status']);
        self::assertArrayHasKey('result', $response['body']);
        $text = (string) ($response['body']['result']['content'][0]['text'] ?? '');
        self::assertNotSame('', $text);
    }

    public function testDangerousToolIsDeniedByPipeline(): void
    {
        $response = $this->mcp(['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => ['name' => 'resource_delete', 'arguments' => ['id' => 1, 'idempotency_key' => 'mcp-delete-' . bin2hex(random_bytes(6))]]]);
        self::assertSame(200, $response['status']);
        self::assertSame(-32003, $response['body']['error']['code'] ?? null);
    }

    public function testUnknownMethodReturnsJsonRpcError(): void
    {
        $response = $this->mcp(['jsonrpc' => '2.0', 'id' => 5, 'method' => 'unknown/method']);
        self::assertSame(-32601, $response['body']['error']['code'] ?? null);
    }

    public function testMcpDisabledReturnsServiceUnavailable(): void
    {
        self::setting('aibridge_mcp_enabled', '0');
        try {
            $response = $this->mcp(['jsonrpc' => '2.0', 'id' => 6, 'method' => 'tools/list']);
            self::assertSame(503, $response['status']);
        } finally {
            self::setting('aibridge_mcp_enabled', '1');
        }
    }

    /** @return array{status:int,body:array<string,mixed>,headers:array<string,string>} */
    private function mcp(array $body): array
    {
        return (new RestApi(self::$modx))->handle('POST', '/mcp', ['Authorization' => 'Bearer ' . self::$token], $body, '127.0.0.1', []);
    }

    private static function setting(string $key, string $value): void
    {
        $setting = self::$modx->getObject(\MODX\Revolution\modSystemSetting::class, ['key' => $key]);
        if (!$setting) {
            $setting = self::$modx->newObject(\MODX\Revolution\modSystemSetting::class);
            $setting->set('key', $key);
            $setting->set('namespace', 'aibridge');
            $setting->set('area', 'tests');
        }
        $setting->set('value', $value);
        $setting->save();
        self::$modx->config[$key] = $value;
        self::$modx->getCacheManager()->refresh(['system_settings' => []]);
    }
}
