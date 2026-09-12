<?php

declare(strict_types=1);

namespace AIBridge\Tests\Integration;

use AIBridge\Api\RestApi;
use AIBridge\Configuration\ConfigFactory;
use AIBridge\Manager\OperationsConsoleService;
use AIBridge\Model\AuditEvent;
use AIBridge\Model\Job;
use AIBridge\MultiSite\ProfileService;
use AIBridge\Queue\QueueManager;
use AIBridge\Security\IdempotencyService;
use AIBridge\Security\TokenManager;
use AIBridge\Workflow\ChangeRequestService;
use PHPUnit\Framework\TestCase;

/**
 * Runtime security regression: authentication bypass, scope escalation,
 * profile escape, rate limiting, idempotency replay/conflict and secret leakage.
 */
final class SecurityRegressionRuntimeTest extends TestCase
{
    private static ?\MODX\Revolution\modX $modx = null;
    private static int $profileId = 0;
    private static int $otherProfileId = 0;
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

        self::setting('aibridge_ip_allowlist', '["127.0.0.1"]');
        self::setting('aibridge_require_https', '0');
        self::setting('aibridge_rate_limit_per_minute', '1000');
        self::setting('aibridge_rest_enabled', '1');

        self::$templateId = self::template();

        $suffix = bin2hex(random_bytes(4));
        $profiles = new ProfileService(self::$modx);
        self::$profileId = (int) ($profiles->create(['name' => 'Sec ' . $suffix, 'site_key' => 'sec-' . $suffix])->toArray()['id'] ?? 0);
        self::$otherProfileId = (int) ($profiles->create(['name' => 'SecOther ' . $suffix, 'site_key' => 'sec-other-' . $suffix])->toArray()['id'] ?? 0);
    }

    public function testAuthenticationBypassAttemptsAreRejected(): void
    {
        $response = $this->call('GET', '/capabilities', auth: 'Basic YWRtaW46YWRtaW4=');
        self::assertSame(401, $response['status']);
        self::assertSame('invalid_authorization_scheme', $response['body']['error']['details']['reason'] ?? null);

        self::assertSame(401, $this->call('GET', '/capabilities', auth: 'Bearer ' . '')['status']);
        self::assertSame(401, $this->call('GET', '/capabilities', auth: 'Bearer not-a-real-token')['status']);

        $revoked = $this->issueToken(['site:read']);
        (new OperationsConsoleService(self::$modx))->setTokenStatus((int) $revoked['id'], 'revoked');
        $revokedResponse = $this->call('GET', '/capabilities', auth: 'Bearer ' . $revoked['token']);
        self::assertSame(401, $revokedResponse['status']);
        self::assertSame('token_inactive', $revokedResponse['body']['error']['details']['reason'] ?? null);

        $expired = $this->issueToken(['site:read'], -1);
        $expiredResponse = $this->call('GET', '/capabilities', auth: 'Bearer ' . $expired['token']);
        self::assertSame(401, $expiredResponse['status']);
        self::assertSame('token_expired', $expiredResponse['body']['error']['details']['reason'] ?? null);

        foreach ([$revokedResponse, $expiredResponse] as $leak) {
            self::assertStringNotContainsString((string) $revoked['token'], json_encode($leak));
            self::assertStringNotContainsString((string) $expired['token'], json_encode($leak));
        }
    }

    public function testScopeEscalationIsDenied(): void
    {
        $readOnly = $this->issueToken(['site:read']);
        $writeOnly = $this->issueToken(['resource:write']);
        $validateOnly = $this->issueToken(['content:validate']);

        $validate = $this->call('POST', '/content/validate', ['content' => [], 'contract' => []], ['Idempotency-Key' => 'sec-' . bin2hex(random_bytes(6))], $readOnly['token']);
        self::assertSame(403, $validate['status']);
        self::assertSame('insufficient_scope', $validate['body']['error']['code'] ?? null);

        $create = $this->call('POST', '/resources', ['pagetitle' => 'x', 'template' => self::$templateId], ['Idempotency-Key' => 'sec-' . bin2hex(random_bytes(6))], $readOnly['token']);
        self::assertSame(403, $create['status']);

        $delete = $this->call('DELETE', '/resources/1', [], ['Idempotency-Key' => 'sec-' . bin2hex(random_bytes(6))], $writeOnly['token']);
        self::assertSame(403, $delete['status']);
        self::assertSame('insufficient_scope', $delete['body']['error']['code'] ?? null);

        $publish = $this->call('POST', '/resources/1/publish', [], ['Idempotency-Key' => 'sec-' . bin2hex(random_bytes(6))], $writeOnly['token']);
        self::assertSame(403, $publish['status']);
        self::assertSame('insufficient_scope', $publish['body']['error']['code'] ?? null);

        $update = $this->call('PATCH', '/resources/1', ['pagetitle' => 'x'], ['Idempotency-Key' => 'sec-' . bin2hex(random_bytes(6))], $validateOnly['token']);
        self::assertSame(403, $update['status']);
    }

    public function testBodyProfileIdCannotEscapePrincipalProfile(): void
    {
        $token = $this->issueToken(['resource:write']);
        $response = $this->call('POST', '/resources', [
            'pagetitle' => 'Security escape ' . bin2hex(random_bytes(3)),
            'alias' => 'sec-escape-' . bin2hex(random_bytes(5)),
            'template' => self::$templateId,
            'content' => '<h1>Escape</h1><p>body</p>',
            'profile_id' => self::$otherProfileId,
        ], ['Idempotency-Key' => 'sec-' . bin2hex(random_bytes(6))], $token['token']);

        self::assertSame(202, $response['status']);
        $jobId = (int) ($response['body']['job_id'] ?? 0);
        self::assertGreaterThan(0, $jobId);

        try {
            $job = self::$modx->getObject(Job::class, $jobId);
            self::assertNotNull($job);
            self::assertSame(self::$profileId, (int) $job->get('profile_id'), 'Client-supplied profile_id must not override the token profile.');
            $payload = json_decode((string) $job->get('payload_json'), true);
            self::assertSame(self::$profileId, (int) ($payload['principal']['profile_id'] ?? 0));
            self::assertNotSame(self::$otherProfileId, (int) ($payload['principal']['profile_id'] ?? 0));
        } finally {
            (new QueueManager(self::$modx))->cancel($jobId, 'test_cleanup');
        }
    }

    public function testRateLimitReturns429WithRetryAfter(): void
    {
        $token = $this->issueToken(['site:read']);
        self::setting('aibridge_rate_limit_per_minute', '2');
        try {
            self::assertSame(200, $this->call('GET', '/capabilities', token: $token['token'])['status']);
            self::assertSame(200, $this->call('GET', '/capabilities', token: $token['token'])['status']);

            $limited = $this->call('GET', '/capabilities', token: $token['token']);
            self::assertSame(429, $limited['status']);
            self::assertSame('rate_limited', $limited['body']['error']['code'] ?? null);
            self::assertArrayHasKey('Retry-After', $limited['headers']);
            self::assertGreaterThan(0, (int) $limited['headers']['Retry-After']);
        } finally {
            self::setting('aibridge_rate_limit_per_minute', '1000');
        }
    }

    public function testIdempotencyConflictAndReplay(): void
    {
        $service = new IdempotencyService(self::$modx);
        $key = 'sec-idem-' . bin2hex(random_bytes(8));
        $principal = 'sec-principal';
        $operation = 'resource.create';
        $hashA = hash('sha256', 'payload-a');
        $hashB = hash('sha256', 'payload-b');

        $first = $service->begin($key, $principal, $operation, $hashA, self::$profileId);
        self::assertTrue((bool) ($first['accepted'] ?? false));

        $conflict = $service->begin($key, $principal, $operation, $hashB, self::$profileId);
        self::assertTrue((bool) ($conflict['conflict'] ?? false));
        self::assertFalse((bool) ($conflict['accepted'] ?? true));

        $inProgressReplay = $service->begin($key, $principal, $operation, $hashA, self::$profileId);
        self::assertTrue((bool) ($inProgressReplay['replay'] ?? false));

        $service->complete($key, $principal, $operation, ['success' => true, 'resource_id' => 42], self::$profileId);
        $completed = $service->begin($key, $principal, $operation, $hashA, self::$profileId);
        self::assertTrue((bool) ($completed['replay'] ?? false));
        self::assertSame(42, (int) ($completed['response']['resource_id'] ?? 0));
    }

    public function testSecretsAreNotLeakedInResponsesOrAudit(): void
    {
        $issued = $this->issueToken(['resource:write']);
        $plain = (string) $issued['token'];
        $hash = hash('sha256', $plain);

        $denied = $this->call('GET', '/capabilities', auth: 'Bearer ' . $plain . 'x');
        self::assertStringNotContainsString($plain, json_encode($denied));
        self::assertStringNotContainsString($hash, json_encode($denied));

        (new ChangeRequestService(self::$modx))->create(
            'resource.update',
            ['pagetitle' => 'secret scan'],
            ['type' => 'token', 'id' => (string) $issued['id'], 'scopes' => ['resource:write'], 'profile_id' => self::$profileId],
            ['request_id' => bin2hex(random_bytes(16))]
        );

        foreach (self::$modx->getCollection(AuditEvent::class, [], ['limit' => 500]) ?: [] as $event) {
            $contextJson = (string) $event->get('context_json');
            self::assertStringNotContainsString($plain, $contextJson);
            self::assertStringNotContainsString($hash, $contextJson);
        }

        $tokens = (new OperationsConsoleService(self::$modx))->tokens(500);
        self::assertNotEmpty($tokens);
        foreach ($tokens as $item) {
            self::assertArrayNotHasKey('token_hash', $item);
            self::assertArrayNotHasKey('token', $item);
        }
    }

    /** @return array<string,mixed> */
    private function issueToken(array $scopes, int $ttlDays = 90): array
    {
        return (new TokenManager(self::$modx, ConfigFactory::fromModx(self::$modx)))
            ->issue('sec-' . bin2hex(random_bytes(4)), $scopes, self::$profileId, $ttlDays);
    }

    /** @return array{status:int,body:array<string,mixed>,headers:array<string,string>} */
    private function call(string $method, string $path, array $body = [], array $headers = [], ?string $token = null, string $auth = ''): array
    {
        if ($auth !== '') {
            $headers['Authorization'] = $auth;
        } elseif ($token !== null && $token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        return (new RestApi(self::$modx))->handle($method, $path, $headers, $body, '127.0.0.1', []);
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
        $template = self::$modx->getObject(\MODX\Revolution\modTemplate::class, ['templatename' => 'AIBridgeSecurityE2E']);
        if (!$template) {
            $template = self::$modx->newObject(\MODX\Revolution\modTemplate::class);
            $template->fromArray(['templatename' => 'AIBridgeSecurityE2E', 'content' => '[[*content]]', 'createdon' => time(), 'editedon' => time()]);
            $template->save();
        }
        return (int) $template->get('id');
    }
}
