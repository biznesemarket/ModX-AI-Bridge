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

final class RestApiTest extends TestCase
{
    private static ?\MODX\Revolution\modX $modx = null;
    private static int $profileId = 0;
    private static int $otherProfileId = 0;
    private static int $templateId = 0;
    private static string $token = '';
    private static string $readOnlyToken = '';

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
        self::setting('aibridge_ip_allowlist', '["127.0.0.1"]');
        self::setting('aibridge_require_https', '0');
        self::setting('aibridge_rate_limit_per_minute', '1000');

        self::$templateId = self::template();

        $suffix = bin2hex(random_bytes(4));
        $profiles = new ProfileService(self::$modx);
        self::$profileId = (int) ($profiles->create([
            'name' => 'RestApiTest ' . $suffix,
            'site_key' => 'rest-api-test-' . $suffix,
        ])->toArray()['id'] ?? 0);
        self::$otherProfileId = (int) ($profiles->create([
            'name' => 'RestApiTestOther ' . $suffix,
            'site_key' => 'rest-api-test-other-' . $suffix,
        ])->toArray()['id'] ?? 0);

        $tokens = new TokenManager(self::$modx, ConfigFactory::fromModx(self::$modx));
        self::$token = (string) $tokens->issue('rest-test-' . $suffix, [
            'site:read', 'content:validate', 'resource:write', 'resource:preview', 'resource:read', 'resource:delete', 'resource:publish',
        ], self::$profileId)['token'];
        self::$readOnlyToken = (string) $tokens->issue('rest-readonly-' . $suffix, ['site:read'], self::$profileId)['token'];
    }

    public function testHealthIsPublic(): void
    {
        $response = $this->call('GET', '/health', auth: false);
        self::assertSame(200, $response['status']);
        self::assertSame('ok', $response['body']['status'] ?? null);
    }

    public function testCapabilitiesRequiresAuthentication(): void
    {
        $response = $this->call('GET', '/capabilities', auth: false);
        self::assertSame(401, $response['status']);
        self::assertSame('authentication_failed', $response['body']['error']['code'] ?? null);
    }

    public function testCapabilitiesWithScopedToken(): void
    {
        $response = $this->call('GET', '/capabilities');
        self::assertSame(200, $response['status']);
        self::assertTrue((bool) ($response['body']['success'] ?? false));
        self::assertNotEmpty($response['body']['data'] ?? []);
    }

    public function testSiteSchemaAndFingerprint(): void
    {
        $schema = $this->call('GET', '/site/schema');
        self::assertSame(200, $schema['status']);
        self::assertTrue((bool) ($schema['body']['success'] ?? false));

        $fingerprint = $this->call('GET', '/site/fingerprint');
        self::assertSame(200, $fingerprint['status']);
        self::assertNotEmpty($fingerprint['body']['data']['fingerprint'] ?? null);
    }

    public function testReadOnlyTokenCannotValidateContent(): void
    {
        $response = $this->call('POST', '/content/validate', [
            'content' => ['pagetitle' => 'x'],
            'contract' => ['fields' => []],
        ], token: self::$readOnlyToken);
        self::assertSame(403, $response['status']);
    }

    public function testContentValidate(): void
    {
        $response = $this->call('POST', '/content/validate', [
            'content' => ['pagetitle' => 'A valid page title'],
            'contract' => ['fields' => ['pagetitle' => ['type' => 'string', 'required' => true, 'max_length' => 255]]],
        ]);
        self::assertSame(200, $response['status']);
        self::assertTrue((bool) ($response['body']['data']['valid'] ?? false));
    }

    public function testMutationRequiresIdempotencyKey(): void
    {
        $response = $this->call('POST', '/resources', ['pagetitle' => 'x', 'template' => self::$templateId]);
        self::assertSame(400, $response['status']);
        self::assertSame('idempotency_key_required', $response['body']['error']['code'] ?? null);
    }

    public function testDeleteIsDeniedByDefaultPolicy(): void
    {
        $response = $this->call('DELETE', '/resources/1', [], ['Idempotency-Key' => 'rest-del-' . bin2hex(random_bytes(6))]);
        self::assertSame(403, $response['status']);
        self::assertSame('operation_disabled', $response['body']['error']['code'] ?? null);
    }

    public function testPublishRequiresApproval(): void
    {
        $response = $this->call('POST', '/resources/1/publish', [], ['Idempotency-Key' => 'rest-pub-' . bin2hex(random_bytes(6))]);
        self::assertSame(403, $response['status']);
        self::assertSame('approval_required', $response['body']['error']['code'] ?? null);
    }

    public function testResourceCreateFlowsThroughQueueAndVerification(): void
    {
        $alias = 'rest-create-' . bin2hex(random_bytes(5));
        $response = $this->call('POST', '/resources', [
            'pagetitle' => 'REST API integration page',
            'alias' => $alias,
            'template' => self::$templateId,
            'content' => '<h1>REST API integration page</h1><p>Body</p>',
        ], ['Idempotency-Key' => 'rest-create-' . bin2hex(random_bytes(6))]);

        self::assertSame(202, $response['status']);
        $jobId = (int) ($response['body']['job_id'] ?? 0);
        self::assertGreaterThan(0, $jobId);

        $job = $this->processJob($jobId);
        self::assertSame('completed', $job['status'] ?? null, (string) ($job['error_json'] ?? ''));

        $resource = self::$modx->getObject(\MODX\Revolution\modResource::class, ['alias' => $alias]);
        self::assertNotNull($resource);
        self::assertSame('REST API integration page', (string) $resource->get('pagetitle'));
    }

    public function testResourceReadBackAfterMutation(): void
    {
        $alias = 'rest-read-' . bin2hex(random_bytes(5));
        $response = $this->call('POST', '/resources', [
            'pagetitle' => 'REST read-back page',
            'alias' => $alias,
            'template' => self::$templateId,
            'content' => '<h1>REST read-back page</h1>',
        ], ['Idempotency-Key' => 'rest-read-' . bin2hex(random_bytes(6))]);
        self::assertSame(202, $response['status']);
        self::assertSame('completed', $this->processJob((int) $response['body']['job_id'])['status'] ?? null);

        $resource = self::$modx->getObject(\MODX\Revolution\modResource::class, ['alias' => $alias]);
        self::assertNotNull($resource);
        $id = (int) $resource->get('id');

        $read = $this->call('GET', '/resources/' . $id);
        self::assertSame(200, $read['status']);
        self::assertTrue((bool) ($read['body']['success'] ?? false));
        self::assertSame($id, $read['body']['data']['resource']['id'] ?? null);
        self::assertSame('REST read-back page', $read['body']['data']['resource']['pagetitle'] ?? null);
        self::assertSame($alias, $read['body']['data']['resource']['alias'] ?? null);
        self::assertIsBool($read['body']['data']['resource']['published'] ?? null);
    }

    public function testResourceReadRequiresScopeAndReturnsNotFound(): void
    {
        $missing = $this->call('GET', '/resources/99999999');
        self::assertSame(404, $missing['status']);
        self::assertSame('resource_not_found', $missing['body']['error']['code'] ?? null);

        $scoped = $this->call('GET', '/resources/1', token: self::$readOnlyToken);
        self::assertSame(403, $scoped['status']);
        self::assertSame('insufficient_scope', $scoped['body']['error']['code'] ?? null);
    }

    public function testIdempotentReplayDoesNotCreateDuplicateResource(): void
    {
        $alias = 'rest-idem-' . bin2hex(random_bytes(5));
        $key = 'rest-idem-' . bin2hex(random_bytes(6));
        $payload = [
            'pagetitle' => 'REST idempotent page',
            'alias' => $alias,
            'template' => self::$templateId,
            'content' => '<h1>REST idempotent page</h1><p>Body</p>',
        ];

        $first = $this->call('POST', '/resources', $payload, ['Idempotency-Key' => $key]);
        self::assertSame(202, $first['status']);
        self::assertSame('completed', $this->processJob((int) $first['body']['job_id'])['status'] ?? null);

        $second = $this->call('POST', '/resources', $payload, ['Idempotency-Key' => $key]);
        self::assertSame(202, $second['status']);
        self::assertSame('completed', $this->processJob((int) $second['body']['job_id'])['status'] ?? null);

        $count = self::$modx->getCount(\MODX\Revolution\modResource::class, ['alias' => $alias]);
        self::assertSame(1, $count);
    }

    public function testProfileIsolationForJobStatus(): void
    {
        $otherToken = (new TokenManager(self::$modx, ConfigFactory::fromModx(self::$modx)))
            ->issue('rest-iso-' . bin2hex(random_bytes(4)), ['site:read'], self::$otherProfileId);

        $job = new Job('resource_execution', ['operation' => 'resource.preview', 'input' => []], 3, 300, 'rest-iso-' . bin2hex(random_bytes(6)), 'isolation-test', null, null, self::$otherProfileId);
        $jobId = (int) (new QueueManager(self::$modx))->dispatch($job);

        try {
            $response = $this->call('GET', '/jobs/' . $jobId, token: (string) $otherToken['token']);
            self::assertSame(200, $response['status'], 'The owning profile must see its own job.');

            $foreign = $this->call('GET', '/jobs/' . $jobId);
            self::assertSame(404, $foreign['status']);
            self::assertSame('job_not_found', $foreign['body']['error']['code'] ?? null);
        } finally {
            (new QueueManager(self::$modx))->cancel($jobId, 'test_cleanup');
        }
    }

    public function testRestDisabledReturnsServiceUnavailable(): void
    {
        self::setting('aibridge_rest_enabled', '0');
        try {
            $response = $this->call('GET', '/capabilities');
            self::assertSame(503, $response['status']);
            self::assertSame('rest_disabled', $response['body']['error']['code'] ?? null);
        } finally {
            self::setting('aibridge_rest_enabled', '1');
        }
    }

    /** @return array{status:int,body:array<string,mixed>,headers:array<string,string>} */
    private function call(string $method, string $path, array $body = [], array $headers = [], string $ip = '127.0.0.1', ?string $token = null, bool $auth = true): array
    {
        if ($auth && $token === null) {
            $token = self::$token;
        }
        if ($token !== null && $token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        return (new RestApi(self::$modx))->handle($method, $path, $headers, $body, $ip, []);
    }

    /** @return array<string,mixed> */
    private function processJob(int $jobId): array
    {
        $queue = new QueueManager(self::$modx);
        $registry = new JobRegistry();
        $registry->register('resource_execution', new ResourceExecutionJobHandler(self::$modx));
        $worker = new Worker($queue, $registry, new AuditService(self::$modx));

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $job = $queue->get($jobId);
            if ($job !== null && $job->status() !== 'queued') {
                return $job->raw();
            }
            $worker->runOnce('rest-test-worker');
        }

        $job = $queue->get($jobId);
        return $job !== null ? $job->raw() : [];
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
        $template = self::$modx->getObject(\MODX\Revolution\modTemplate::class, ['templatename' => 'AIBridgeRestApiTest']);
        if (!$template) {
            $template = self::$modx->newObject(\MODX\Revolution\modTemplate::class);
            $template->fromArray([
                'templatename' => 'AIBridgeRestApiTest',
                'content' => '[[*content]]',
                'createdon' => time(),
                'editedon' => time(),
            ]);
            $template->save();
        }
        return (int) $template->get('id');
    }
}
