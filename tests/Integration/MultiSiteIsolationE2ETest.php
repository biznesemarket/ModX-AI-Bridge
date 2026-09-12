<?php

declare(strict_types=1);

namespace AIBridge\Tests\Integration;

use AIBridge\Api\RestApi;
use AIBridge\Configuration\ConfigFactory;
use AIBridge\Execution\ResourceExecutionService;
use AIBridge\Manager\OperationsConsoleService;
use AIBridge\Model\AuditEvent;
use AIBridge\MultiSite\ProfileService;
use AIBridge\Queue\Job;
use AIBridge\Queue\QueueManager;
use AIBridge\Security\TokenAuthenticator;
use AIBridge\Security\TokenManager;
use AIBridge\Verification\RollbackService;
use AIBridge\Workflow\ApprovalService;
use AIBridge\Workflow\ChangeState;
use AIBridge\Workflow\ChangeExecutionService;
use AIBridge\Workflow\ChangeRequestService;
use PHPUnit\Framework\TestCase;

/**
 * Multi-site isolation matrix across Token, Job, Audit, Schema, Fingerprint,
 * Snapshot, Change and Approval. Every cross-profile access must be denied on
 * the server side (no client-supplied profile_id is trusted).
 */
final class MultiSiteIsolationE2ETest extends TestCase
{
    private static ?\MODX\Revolution\modX $modx = null;
    private static int $profileA = 0;
    private static int $profileB = 0;
    private static int $templateId = 0;
    private static string $tokenA = '';
    private static string $tokenB = '';

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
        self::$profileA = (int) ($profiles->create(['name' => 'Iso A ' . $suffix, 'site_key' => 'iso-a-' . $suffix])->toArray()['id'] ?? 0);
        self::$profileB = (int) ($profiles->create(['name' => 'Iso B ' . $suffix, 'site_key' => 'iso-b-' . $suffix])->toArray()['id'] ?? 0);

        $tokens = new TokenManager(self::$modx, ConfigFactory::fromModx(self::$modx));
        self::$tokenA = (string) $tokens->issue('iso-a-' . $suffix, self::scopes(), self::$profileA)['token'];
        self::$tokenB = (string) $tokens->issue('iso-b-' . $suffix, self::scopes(), self::$profileB)['token'];
    }

    public function testTokenAuthenticationCarriesOwningProfile(): void
    {
        $authenticator = new TokenAuthenticator(self::$modx);

        $a = $authenticator->authenticate('Bearer ' . self::$tokenA);
        self::assertTrue($a['authenticated'] ?? false);
        self::assertSame(self::$profileA, (int) ($a['principal']['profile_id'] ?? 0));

        $b = $authenticator->authenticate('Bearer ' . self::$tokenB);
        self::assertTrue($b['authenticated'] ?? false);
        self::assertSame(self::$profileB, (int) ($b['principal']['profile_id'] ?? 0));
    }

    public function testTokenIssuanceRequiresProfile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new TokenManager(self::$modx, ConfigFactory::fromModx(self::$modx)))->issue('no-profile', ['site:read'], 0);
    }

    public function testInactiveProfileTokenIsRejected(): void
    {
        $console = new OperationsConsoleService(self::$modx);
        $authenticator = new TokenAuthenticator(self::$modx);

        self::assertTrue($console->setProfileStatus(self::$profileB, 'disabled'));
        try {
            $result = $authenticator->authenticate('Bearer ' . self::$tokenB);
            self::assertFalse($result['authenticated'] ?? true);
            self::assertSame('profile_inactive', $result['reason'] ?? null);
        } finally {
            self::assertTrue($console->setProfileStatus(self::$profileB, 'active'));
        }

        self::assertTrue($authenticator->authenticate('Bearer ' . self::$tokenB)['authenticated'] ?? false);
    }

    public function testJobStatusIsProfileScoped(): void
    {
        $job = new Job('resource_execution', ['operation' => 'resource.preview', 'input' => []], 3, 300, 'iso-job-' . bin2hex(random_bytes(6)), 'iso', null, null, self::$profileA);
        $jobId = (int) (new QueueManager(self::$modx))->dispatch($job);

        try {
            $own = $this->call('GET', '/jobs/' . $jobId, token: self::$tokenA);
            self::assertSame(200, $own['status']);

            $foreign = $this->call('GET', '/jobs/' . $jobId, token: self::$tokenB);
            self::assertSame(404, $foreign['status']);
            self::assertSame('job_not_found', $foreign['body']['error']['code'] ?? null);
        } finally {
            (new QueueManager(self::$modx))->cancel($jobId, 'test_cleanup');
        }
    }

    public function testProfilesEndpointIsScopedToTokenProfile(): void
    {
        $a = $this->call('GET', '/profiles', token: self::$tokenA);
        self::assertSame(200, $a['status']);
        $idsA = array_column($a['body']['data']['profiles'] ?? [], 'id');
        self::assertSame([self::$profileA], array_map('intval', $idsA));

        $b = $this->call('GET', '/profiles', token: self::$tokenB);
        self::assertSame(200, $b['status']);
        $idsB = array_column($b['body']['data']['profiles'] ?? [], 'id');
        self::assertSame([self::$profileB], array_map('intval', $idsB));
    }

    public function testSnapshotRollbackIsProfileScoped(): void
    {
        $resourceId = $this->createResource();
        $resource = self::$modx->getObject(\MODX\Revolution\modResource::class, $resourceId);
        $resource->set('pagetitle', 'Isolation original');
        $resource->save();

        $update = (new ResourceExecutionService(self::$modx))->update(
            ['id' => $resourceId, 'pagetitle' => 'Isolation mutated'],
            $this->principal(self::$profileA, ['resource:write']),
            ['ip' => '127.0.0.1', 'request_id' => bin2hex(random_bytes(16)), 'idempotency_key' => 'iso-rollback-' . bin2hex(random_bytes(8))]
        );
        self::assertTrue((bool) ($update['success'] ?? false), json_encode($update));
        $snapshotId = (int) ($update['data']['snapshot']['id'] ?? 0);
        self::assertGreaterThan(0, $snapshotId);

        $denied = (new RollbackService(self::$modx))->rollback($snapshotId, $this->principal(self::$profileB, ['resource:rollback']), [
            'ip' => '127.0.0.1',
            'request_id' => bin2hex(random_bytes(8)),
        ]);
        self::assertFalse((bool) ($denied['success'] ?? true), json_encode($denied));
        self::assertSame('profile_mismatch', $denied['code'] ?? null);

        $resource = self::$modx->getObject(\MODX\Revolution\modResource::class, $resourceId);
        self::assertSame('Isolation mutated', (string) $resource->get('pagetitle'));

        $allowed = (new RollbackService(self::$modx))->rollback($snapshotId, $this->principal(self::$profileA, ['resource:rollback']), [
            'ip' => '127.0.0.1',
            'request_id' => bin2hex(random_bytes(8)),
        ]);
        self::assertTrue((bool) ($allowed['success'] ?? false), json_encode($allowed));
        $resource = self::$modx->getObject(\MODX\Revolution\modResource::class, $resourceId);
        self::assertSame('Isolation original', (string) $resource->get('pagetitle'));
    }

    public function testChangeAndApprovalAreProfileScoped(): void
    {
        $changes = new ChangeRequestService(self::$modx);
        $approvals = new ApprovalService(self::$modx);
        $principalA = $this->principal(self::$profileA, ['resource:write']);
        $principalB = $this->principal(self::$profileB, ['resource:write']);

        $resourceId = $this->createResource();
        $change = $changes->create('resource.update', ['id' => $resourceId, 'pagetitle' => 'Isolation change'], $principalA, ['request_id' => bin2hex(random_bytes(16))]);
        $changeA = (int) $change['id'];

        // Another profile cannot advance the change.
        $this->assertThrowsMessage(fn () => $changes->submit($changeA, $principalB), 'another profile');

        $changes->submit($changeA, $principalA);
        $this->assertThrowsMessage(fn () => (new ApprovalService(self::$modx))->create($changeA, $principalB), 'another profile');

        $approvalId = (int) $approvals->create($changeA, $principalA)['id'];
        $this->assertThrowsMessage(fn () => $approvals->decide($approvalId, 'approved', $principalB), 'another profile');

        $approvals->decide($approvalId, 'approved', $principalA);
        $this->assertThrowsMessage(fn () => $changes->approve($changeA, $approvalId, $principalB), 'another profile');

        $changes->approve($changeA, $approvalId, $principalA);
        self::assertSame(ChangeState::APPROVED, (string) $changes->get($changeA)['status']);
        $this->assertThrowsMessage(
            fn () => (new ChangeExecutionService(self::$modx))->dispatchApproved($changeA, $principalB),
            'another profile'
        );
    }

    public function testAuditRowsCarryOwningProfile(): void
    {
        $changes = new ChangeRequestService(self::$modx);
        $principalA = $this->principal(self::$profileA, ['resource:write']);
        $changeId = (int) $changes->create('resource.update', ['id' => $this->createResource(), 'pagetitle' => 'Isolation audit'], $principalA, ['request_id' => bin2hex(random_bytes(16))])['id'];

        self::assertSame(self::$profileA, $this->auditProfileForChange('change_created', $changeId));
        self::assertSame(0, $this->auditCountForChangeProfile('change_created', $changeId, self::$profileB));
    }

    public function testSchemaAndFingerprintRemainSiteScoped(): void
    {
        $a = $this->call('GET', '/site/schema', token: self::$tokenA);
        $b = $this->call('GET', '/site/schema', token: self::$tokenB);
        self::assertSame(200, $a['status']);
        self::assertSame(200, $b['status']);
        self::assertSame(
            $a['body']['data']['fingerprint'] ?? null,
            $b['body']['data']['fingerprint'] ?? null,
            'Site schema/fingerprint is site-level and must not diverge by profile token.'
        );
    }

    /** @return list<string> */
    private static function scopes(): array
    {
        return ['site:read', 'content:validate', 'resource:write', 'resource:preview', 'resource:rollback'];
    }

    /** @return array<string,mixed> */
    private function principal(int $profileId, array $scopes): array
    {
        return ['type' => 'token', 'id' => 'iso-token-' . $profileId, 'scopes' => $scopes, 'profile_id' => $profileId];
    }

    private function auditProfileForChange(string $event, int $changeId): ?int
    {
        foreach (self::$modx->getCollection(AuditEvent::class, ['event' => $event]) ?: [] as $row) {
            $context = json_decode((string) $row->get('context_json'), true);
            if ((int) ($context['change_id'] ?? 0) === $changeId) {
                return (int) $row->get('profile_id');
            }
        }
        return null;
    }

    private function auditCountForChangeProfile(string $event, int $changeId, int $profileId): int
    {
        $count = 0;
        foreach (self::$modx->getCollection(AuditEvent::class, ['event' => $event, 'profile_id' => $profileId]) ?: [] as $row) {
            $context = json_decode((string) $row->get('context_json'), true);
            if ((int) ($context['change_id'] ?? 0) === $changeId) {
                $count++;
            }
        }
        return $count;
    }

    private function assertThrowsMessage(callable $callback, string $expectedMessage): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            self::assertStringContainsString($expectedMessage, $e->getMessage());
            return;
        }
        self::fail('Expected exception was not thrown: ' . $expectedMessage);
    }

    /** @return array{status:int,body:array<string,mixed>,headers:array<string,string>} */
    private function call(string $method, string $path, array $body = [], array $headers = [], string $ip = '127.0.0.1', ?string $token = null): array
    {
        if ($token !== null && $token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        return (new RestApi(self::$modx))->handle($method, $path, $headers, $body, $ip, []);
    }

    private function createResource(): int
    {
        $resource = self::$modx->newObject(\MODX\Revolution\modResource::class);
        $resource->fromArray([
            'pagetitle' => 'Isolation ' . bin2hex(random_bytes(4)),
            'alias' => 'isolation-' . bin2hex(random_bytes(5)),
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
            throw new \RuntimeException('Failed to create the isolation resource.');
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
        $template = self::$modx->getObject(\MODX\Revolution\modTemplate::class, ['templatename' => 'AIBridgeIsolationE2E']);
        if (!$template) {
            $template = self::$modx->newObject(\MODX\Revolution\modTemplate::class);
            $template->fromArray([
                'templatename' => 'AIBridgeIsolationE2E',
                'content' => '[[*content]]',
                'createdon' => time(),
                'editedon' => time(),
            ]);
            $template->save();
        }
        return (int) $template->get('id');
    }
}
