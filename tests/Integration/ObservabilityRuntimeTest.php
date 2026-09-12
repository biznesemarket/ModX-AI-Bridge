<?php

declare(strict_types=1);

namespace AIBridge\Tests\Integration;

use AIBridge\Api\RestApi;
use AIBridge\Audit\AuditService;
use AIBridge\Configuration\ConfigFactory;
use AIBridge\MultiSite\ProfileService;
use AIBridge\Queue\Job;
use AIBridge\Queue\JobRegistry;
use AIBridge\Queue\QueueManager;
use AIBridge\Queue\ResourceExecutionJobHandler;
use AIBridge\Queue\Worker;
use AIBridge\Security\TokenManager;
use PHPUnit\Framework\TestCase;

/**
 * Runtime observability: inbound request-id propagation, structured error
 * envelopes, worker audit correlation and the public readiness endpoint.
 */
final class ObservabilityRuntimeTest extends TestCase
{
    private static ?\MODX\Revolution\modX $modx = null;
    private static int $profileId = 0;
    private static int $templateId = 0;
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

        self::setting('aibridge_ip_allowlist', '["127.0.0.1"]');
        self::setting('aibridge_require_https', '0');
        self::setting('aibridge_rate_limit_per_minute', '1000');
        self::setting('aibridge_rest_enabled', '1');

        self::$templateId = self::template();

        $suffix = bin2hex(random_bytes(4));
        self::$profileId = (int) ((new ProfileService(self::$modx))->create([
            'name' => 'Observability ' . $suffix,
            'site_key' => 'observability-' . $suffix,
        ])->toArray()['id'] ?? 0);

        self::$token = (string) (new TokenManager(self::$modx, ConfigFactory::fromModx(self::$modx)))
            ->issue('obs-' . $suffix, ['site:read', 'content:validate', 'resource:preview'], self::$profileId)['token'];
    }

    public function testInboundRequestIdIsPropagatedAndInvalidOneIsReplaced(): void
    {
        $ok = $this->call('GET', '/health', headers: ['X-Request-Id' => 'obs-req-1']);
        self::assertSame(200, $ok['status']);
        self::assertSame('obs-req-1', $ok['body']['request_id'] ?? null);
        self::assertSame('obs-req-1', $ok['headers']['X-Request-Id'] ?? null);

        $invalid = $this->call('GET', '/health', headers: ['X-Request-Id' => 'bad id with spaces!']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', (string) ($invalid['body']['request_id'] ?? ''));
        self::assertNotSame('bad id with spaces!', $invalid['body']['request_id'] ?? null);
    }

    public function testStructuredErrorEnvelopeCarriesRequestId(): void
    {
        $response = $this->call('GET', '/capabilities', headers: [
            'X-Request-Id' => 'obs-err-1',
            'Authorization' => 'Bearer definitely-not-valid',
        ]);

        self::assertSame(401, $response['status']);
        self::assertSame('authentication_failed', $response['body']['error']['code'] ?? null);
        self::assertNotSame('', (string) ($response['body']['error']['message'] ?? ''));
        self::assertIsArray($response['body']['error']['details'] ?? null);
        self::assertSame('obs-err-1', $response['body']['error']['request_id'] ?? null);
        self::assertSame('obs-err-1', $response['headers']['X-Request-Id'] ?? null);
    }

    public function testJobAndWorkerAuditPropagateRequestId(): void
    {
        $resourceId = $this->createResource();
        $principal = ['type' => 'token', 'id' => 'obs-principal', 'scopes' => ['resource:preview'], 'profile_id' => self::$profileId];
        $job = new Job(
            'resource_execution',
            ['operation' => 'resource.preview', 'input' => ['id' => $resourceId], 'principal' => $principal, 'request' => ['ip' => '127.0.0.1', 'channel' => 'rest']],
            3,
            300,
            'obs-job-' . bin2hex(random_bytes(6)),
            'obs-principal',
            'obs-job-1',
            null,
            self::$profileId
        );
        $jobId = (int) (new QueueManager(self::$modx))->dispatch($job);

        $record = (new QueueManager(self::$modx))->get($jobId);
        self::assertNotNull($record);
        self::assertSame('obs-job-1', (string) $record->raw()['request_id']);

        $registry = new JobRegistry();
        $registry->register('resource_execution', new ResourceExecutionJobHandler(self::$modx));
        $worker = new Worker(new QueueManager(self::$modx), $registry, new AuditService(self::$modx));
        $result = $worker->runOnce('obs-worker');

        self::assertNotNull($result);
        self::assertSame('completed', $result['status'] ?? null, (string) ($result['error']['message'] ?? ''));
        self::assertGreaterThanOrEqual(
            1,
            self::$modx->getCount(\AIBridge\Model\AuditEvent::class, ['event' => 'job_completed', 'request_id' => 'obs-job-1'])
        );
    }

    public function testReadyEndpointReportsReadiness(): void
    {
        $response = $this->call('GET', '/ready');
        self::assertSame(200, $response['status']);
        self::assertSame('ready', $response['body']['status'] ?? null);
        self::assertTrue((bool) ($response['body']['checks']['database'] ?? false));
        self::assertTrue((bool) ($response['body']['checks']['component_namespace'] ?? false));
        self::assertArrayHasKey('X-Request-Id', $response['headers']);
    }

    /** @return array{status:int,body:array<string,mixed>,headers:array<string,string>} */
    private function call(string $method, string $path, array $body = [], array $headers = []): array
    {
        if (!isset($headers['Authorization'])) {
            $headers['Authorization'] = 'Bearer ' . self::$token;
        }
        return (new RestApi(self::$modx))->handle($method, $path, $headers, $body, '127.0.0.1', []);
    }

    private function createResource(): int
    {
        $resource = self::$modx->newObject(\MODX\Revolution\modResource::class);
        $resource->fromArray([
            'pagetitle' => 'Observability ' . bin2hex(random_bytes(4)),
            'alias' => 'observability-' . bin2hex(random_bytes(5)),
            'template' => self::$templateId,
            'content' => '<h1>Initial</h1><p>body</p>',
            'published' => 0,
            'deleted' => 0,
            'hidemenu' => 1,
            'context_key' => 'web',
            'createdon' => time(),
            'editedon' => time(),
        ]);
        if (!$resource->save()) {
            throw new \RuntimeException('Failed to create the observability resource.');
        }
        return (int) $resource->get('id');
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

    private static function template(): int
    {
        $template = self::$modx->getObject(\MODX\Revolution\modTemplate::class, ['templatename' => 'AIBridgeObservabilityE2E']);
        if (!$template) {
            $template = self::$modx->newObject(\MODX\Revolution\modTemplate::class);
            $template->fromArray(['templatename' => 'AIBridgeObservabilityE2E', 'content' => '[[*content]]', 'createdon' => time(), 'editedon' => time()]);
            $template->save();
        }
        return (int) $template->get('id');
    }
}
