<?php

declare(strict_types=1);

namespace AIBridge\Tests\Integration;

use AIBridge\Api\RestApi;
use AIBridge\Configuration\ConfigFactory;
use AIBridge\MultiSite\ProfileService;
use AIBridge\Security\TokenManager;
use AIBridge\Workflow\ApprovalService;
use AIBridge\Workflow\ChangeRequestService;
use PHPUnit\Framework\TestCase;

/**
 * MCP publish must forward `approval_id`/`change_id` into the security pipeline,
 * otherwise the approval-gated `resource_publish` tool can never succeed even
 * when a valid approved change request exists.
 */
final class McpPublishApprovalTest extends TestCase
{
    private static ?\MODX\Revolution\modX $modx = null;
    private static int $profileId = 0;
    private static string $token = '';
    private static int $templateId = 0;

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
        self::setting('aibridge_approval_required_for_publish', '1');

        $suffix = bin2hex(random_bytes(4));
        self::$profileId = (int) ((new ProfileService(self::$modx))->create([
            'name' => 'McpPublishApprovalTest ' . $suffix,
            'site_key' => 'mcp-publish-approval-' . $suffix,
        ])->toArray()['id'] ?? 0);

        self::$token = (string) (new TokenManager(self::$modx, ConfigFactory::fromModx(self::$modx)))
            ->issue('mcp-publish-' . $suffix, ['resource:read', 'resource:write', 'resource:publish'], self::$profileId)['token'];

        self::$templateId = self::template();
    }

    public function testPublishToolExecutesWithApprovedChange(): void
    {
        $resourceId = $this->createResource();
        $manager = [
            'id' => '1',
            'type' => 'manager',
            'scopes' => ['*'],
            'manager_authorized' => true,
            'profile_id' => self::$profileId,
        ];

        $changes = new ChangeRequestService(self::$modx);
        $change = $changes->create('resource.publish', ['id' => $resourceId], $manager, ['request_id' => bin2hex(random_bytes(8))], []);
        $changeId = (int) $change['id'];
        $changes->submit($changeId, $manager);

        $approvals = new ApprovalService(self::$modx);
        $approvalId = (int) $approvals->create($changeId, $manager)['id'];
        $approvals->decide($approvalId, 'approved', $manager);

        $response = $this->mcp([
            'jsonrpc' => '2.0',
            'id' => 20,
            'method' => 'tools/call',
            'params' => [
                'name' => 'resource_publish',
                'arguments' => [
                    'id' => $resourceId,
                    'idempotency_key' => 'mcp-publish-' . bin2hex(random_bytes(8)),
                    'approval_id' => (string) $approvalId,
                    'change_id' => $changeId,
                ],
            ],
        ]);

        self::assertSame(200, $response['status']);
        self::assertArrayHasKey('result', $response['body'], json_encode($response['body']));

        $resource = self::$modx->getObject(\MODX\Revolution\modResource::class, $resourceId);
        self::assertNotNull($resource);
        self::assertSame(1, (int) $resource->get('published'));
    }

    public function testPublishToolWithoutApprovalIsDenied(): void
    {
        $resourceId = $this->createResource();
        $response = $this->mcp([
            'jsonrpc' => '2.0',
            'id' => 21,
            'method' => 'tools/call',
            'params' => [
                'name' => 'resource_publish',
                'arguments' => ['id' => $resourceId, 'idempotency_key' => 'mcp-publish-' . bin2hex(random_bytes(8))],
            ],
        ]);

        self::assertSame(-32003, $response['body']['error']['code'] ?? null);
        $resource = self::$modx->getObject(\MODX\Revolution\modResource::class, $resourceId);
        self::assertSame(0, (int) $resource->get('published'));
    }

    /** @return array{status:int,body:array<string,mixed>,headers:array<string,string>} */
    private function mcp(array $body): array
    {
        return (new RestApi(self::$modx))->handle('POST', '/mcp', ['Authorization' => 'Bearer ' . self::$token], $body, '127.0.0.1', []);
    }

    private function createResource(): int
    {
        $resource = self::$modx->newObject(\MODX\Revolution\modResource::class);
        $resource->fromArray([
            'pagetitle' => 'MCP publish ' . bin2hex(random_bytes(4)),
            'alias' => 'mcp-publish-' . bin2hex(random_bytes(5)),
            'template' => self::$templateId,
            'content' => '<h1>MCP publish</h1><p>body</p>',
            'published' => 0,
            'deleted' => 0,
            'hidemenu' => 1,
            'context_key' => 'web',
            'createdon' => time(),
            'editedon' => time(),
        ]);
        if (!$resource->save()) {
            throw new \RuntimeException('Failed to create the publish-test resource.');
        }
        return (int) $resource->get('id');
    }

    private static function template(): int
    {
        $template = self::$modx->getObject(\MODX\Revolution\modTemplate::class, ['templatename' => 'AIBridgeMcpPublishTest']);
        if (!$template) {
            $template = self::$modx->newObject(\MODX\Revolution\modTemplate::class);
            $template->fromArray(['templatename' => 'AIBridgeMcpPublishTest', 'content' => '[[*content]]', 'createdon' => time(), 'editedon' => time()]);
            $template->save();
        }
        return (int) $template->get('id');
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
