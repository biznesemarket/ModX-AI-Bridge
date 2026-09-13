<?php

declare(strict_types=1);

namespace AIBridge\Tests\Integration;

use AIBridge\Configuration\ConfigFactory;
use AIBridge\Manager\OperationsConsoleService;
use AIBridge\MultiSite\ProfileService;
use AIBridge\Security\TokenManager;
use PHPUnit\Framework\TestCase;

final class OperationsConsoleServiceTest extends TestCase
{
    private const MARKER = 'console-test-';

    private static \MODX\Revolution\modX $modx;
    private static string $prefix = '';

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

        self::cleanup();
        self::$prefix = 'console-' . bin2hex(random_bytes(4));
    }

    public function testLimitsAndOrderingAreAppliedToConsoleLists(): void
    {
        $console = new OperationsConsoleService(self::$modx);
        $profiles = new ProfileService(self::$modx);

        for ($i = 0; $i < 3; $i++) {
            $profiles->create(['name' => self::$prefix . ' profile ' . $i, 'site_key' => self::$prefix . '-site-' . $i]);
        }
        self::assertCount(2, $console->profiles(2));
        self::assertCount(1, $console->profiles(1));

        $profileId = (int) ($profiles->create([
            'name' => self::$prefix . ' tokens',
            'site_key' => self::$prefix . '-tokens',
        ])->toArray()['id'] ?? 0);
        $tokens = new TokenManager(self::$modx, ConfigFactory::fromModx(self::$modx));
        $tokens->issue(self::$prefix . '-t1', ['site:read'], $profileId);
        $tokens->issue(self::$prefix . '-t2', ['site:read'], $profileId);
        $tokenRows = $console->tokens(1);
        self::assertCount(1, $tokenRows);
        self::assertArrayNotHasKey('token_hash', $tokenRows[0], 'Token hashes must never be console output.');
        self::assertArrayNotHasKey('token', $tokenRows[0], 'Plaintext tokens must never be console output.');

        self::createPolicy(self::MARKER . 'policy-a');
        self::createPolicy(self::MARKER . 'policy-b');
        self::assertCount(1, $console->policies(1));

        self::createJob('2099-01-01 00:00:00');
        $newerJob = self::createJob('2099-01-02 00:00:00');
        $jobs = $console->jobs(2);
        self::assertCount(2, $jobs);
        self::assertSame($newerJob, (int) $jobs[0]['id']);

        self::createAudit('2099-01-03 00:00:00');
        $newerAudit = self::createAudit('2099-01-04 00:00:00');
        $audit = $console->audit(2);
        self::assertCount(2, $audit);
        self::assertSame($newerAudit, (int) $audit[0]['id']);

        self::createFingerprint('2099-01-05 00:00:00');
        $newerFingerprint = self::createFingerprint('2099-01-06 00:00:00');
        $fingerprints = $console->fingerprints(2);
        self::assertCount(2, $fingerprints);
        self::assertSame($newerFingerprint, (int) $fingerprints[0]['id']);

        self::createChange('2099-01-07 00:00:00');
        $newerChange = self::createChange('2099-01-08 00:00:00');
        $changes = $console->changes(2);
        self::assertCount(2, $changes);
        self::assertSame($newerChange, (int) $changes[0]['id']);

        self::createApproval($newerChange, '2099-01-09 00:00:00');
        $newerApproval = self::createApproval($newerChange, '2099-01-10 00:00:00');
        $approvals = $console->approvals(2);
        self::assertCount(2, $approvals);
        self::assertSame($newerApproval, (int) $approvals[0]['id']);
    }

    public function testReadinessReportsExpectedChecks(): void
    {
        $readiness = (new OperationsConsoleService(self::$modx))->readiness();
        self::assertSame('ready', $readiness['status']);
        foreach (['modx', 'database', 'service_container', 'component_namespace'] as $check) {
            self::assertTrue((bool) ($readiness['checks'][$check] ?? false), $check . ' must be ready.');
        }
    }

    private static function cleanup(): void
    {
        $future = '2099%';
        self::$modx->removeCollection(\AIBridge\Model\Approval::class, ['requested_at:LIKE' => $future]);
        self::$modx->removeCollection(\AIBridge\Model\ChangeRequest::class, ['created_at:LIKE' => $future]);
        self::$modx->removeCollection(\AIBridge\Model\Job::class, ['created_at:LIKE' => $future]);
        self::$modx->removeCollection(\AIBridge\Model\AuditEvent::class, ['created_at:LIKE' => $future]);
        self::$modx->removeCollection(\AIBridge\Model\Fingerprint::class, ['created_at:LIKE' => $future]);
        self::$modx->removeCollection(\AIBridge\Model\Policy::class, ['name:LIKE' => self::MARKER . '%']);
    }

    private static function createPolicy(string $name): int
    {
        $policy = self::$modx->newObject(\AIBridge\Model\Policy::class);
        $policy->fromArray([
            'profile_id' => 1,
            'name' => $name,
            'rules_json' => '{}',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $policy->save();
        return (int) $policy->get('id');
    }

    private static function createJob(string $createdAt): int
    {
        $job = self::$modx->newObject(\AIBridge\Model\Job::class);
        $job->fromArray([
            'profile_id' => 1,
            'type' => self::MARKER . 'job',
            'status' => 'completed',
            'attempts' => 1,
            'max_attempts' => 3,
            'timeout_seconds' => 300,
            'progress' => 100,
            'available_at' => $createdAt,
            'created_at' => $createdAt,
            'finished_at' => $createdAt,
        ]);
        $job->save();
        return (int) $job->get('id');
    }

    private static function createAudit(string $createdAt): int
    {
        $event = self::$modx->newObject(\AIBridge\Model\AuditEvent::class);
        $event->fromArray([
            'profile_id' => 1,
            'event' => self::MARKER . 'event',
            'actor_type' => 'test',
            'actor_id' => self::MARKER . 'actor',
            'created_at' => $createdAt,
        ]);
        $event->save();
        return (int) $event->get('id');
    }

    private static function createFingerprint(string $createdAt): int
    {
        $fingerprint = self::$modx->newObject(\AIBridge\Model\Fingerprint::class);
        $fingerprint->fromArray([
            'profile_id' => 1,
            'algorithm' => self::MARKER . 'sha256',
            'fingerprint' => str_repeat('b', 64),
            'snapshot_json' => '{}',
            'created_at' => $createdAt,
        ]);
        $fingerprint->save();
        return (int) $fingerprint->get('id');
    }

    private static function createChange(string $createdAt): int
    {
        $change = self::$modx->newObject(\AIBridge\Model\ChangeRequest::class);
        $change->fromArray([
            'profile_id' => 1,
            'operation' => 'resource.update',
            'status' => 'draft',
            'input_json' => '{}',
            'requested_by' => self::MARKER . 'requester',
            'created_at' => $createdAt,
        ]);
        $change->save();
        return (int) $change->get('id');
    }

    private static function createApproval(int $changeId, string $requestedAt): int
    {
        $approval = self::$modx->newObject(\AIBridge\Model\Approval::class);
        $approval->fromArray([
            'change_id' => $changeId,
            'status' => 'pending',
            'requested_by' => self::MARKER . 'requester',
            'requested_at' => $requestedAt,
        ]);
        $approval->save();
        return (int) $approval->get('id');
    }
}
