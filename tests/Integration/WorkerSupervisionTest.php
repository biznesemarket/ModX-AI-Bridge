<?php

declare(strict_types=1);

namespace AIBridge\Tests\Integration;

use AIBridge\Audit\AuditService;
use AIBridge\Queue\Job;
use AIBridge\Queue\JobRegistry;
use AIBridge\Queue\JobState;
use AIBridge\Queue\QueueManager;
use AIBridge\Queue\ResourceExecutionJobHandler;
use AIBridge\Queue\Worker;
use AIBridge\Security\IdempotencyService;
use PHPUnit\Framework\TestCase;

final class WorkerSupervisionTest extends TestCase
{
    private static ?\MODX\Revolution\modX $modx = null;

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

        // The worker claims the oldest queued job, so leftovers from interrupted
        // runs must not shadow the jobs this suite dispatches.
        $queue = new QueueManager(self::$modx);
        foreach ([JobState::QUEUED, JobState::RUNNING] as $state) {
            foreach (self::$modx->getCollection(\AIBridge\Model\Job::class, ['status' => $state]) ?: [] as $row) {
                $queue->cancel((int) $row->get('id'), 'WorkerSupervisionTest setup cleanup');
            }
        }
    }

    public function testSupervisorTerminatesOverBudgetJobAndReleasesIdempotencyKey(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $key = 'supervisor-' . $suffix;
        $principal = 'supervisor-test';
        $operation = 'resource.update';

        $queue = new QueueManager(self::$modx);
        $idempotency = new IdempotencyService(self::$modx);
        self::assertTrue((bool) ($idempotency->begin($key, $principal, $operation, 'hash-' . $suffix)['accepted'] ?? false));

        $jobId = (int) $queue->dispatch(new Job(
            'resource_execution',
            [
                'operation' => $operation,
                'input' => ['id' => 1, 'pagetitle' => 'never applied'],
                'principal' => ['id' => $principal, 'type' => 'token', 'scopes' => ['resource:write']],
            ],
            3,
            1,
            $key,
            $principal,
            'supervisor-' . $suffix
        ));

        $result = $this->worker($queue, $idempotency)->runOnceSupervised(
            'supervisor-test',
            [PHP_BINARY, '-r', 'usleep(5000000);']
        );

        self::assertNotNull($result);
        self::assertSame(JobState::FAILED, $result['status'] ?? null);
        self::assertSame('job_timeout', $result['error']['code'] ?? null);

        $job = $queue->get($jobId);
        self::assertNotNull($job);
        self::assertSame(JobState::FAILED, $job->status());
        self::assertSame('job_timeout', json_decode((string) ($job->raw()['error_json'] ?? '{}'), true)['code'] ?? null);

        // The killed attempt produced no side effect, so the key must be reusable.
        $reuse = $idempotency->begin($key, $principal, $operation, 'hash-' . $suffix);
        self::assertTrue((bool) ($reuse['accepted'] ?? false), 'A hard timeout must release the in-progress idempotency key.');
        $idempotency->abandon($key, $principal, $operation);
    }

    public function testSupervisorRunsJobToCompletionInChildProcess(): void
    {
        self::setting('aibridge_ip_allowlist', '["127.0.0.1"]');

        $resource = self::$modx->newObject(\MODX\Revolution\modResource::class);
        $resource->fromArray([
            'pagetitle' => 'Supervisor child probe',
            'alias' => 'supervisor-child-' . bin2hex(random_bytes(4)),
            'content' => '<h1>Supervisor child probe</h1>',
            'published' => 0,
            'deleted' => 0,
            'hidemenu' => 1,
            'context_key' => 'web',
        ]);
        self::assertTrue($resource->save());

        $queue = new QueueManager(self::$modx);
        $jobId = (int) $queue->dispatch(new Job(
            'resource_execution',
            [
                'operation' => 'resource.preview',
                'input' => ['id' => (int) $resource->get('id')],
                'principal' => ['id' => 'supervisor-child', 'type' => 'token', 'scopes' => ['resource:preview']],
                'request' => ['ip' => '127.0.0.1', 'channel' => 'rest'],
            ],
            3,
            30,
            '',
            'supervisor-child',
            'supervisor-child-' . bin2hex(random_bytes(4))
        ));

        $workerScript = dirname(__DIR__, 2) . '/core/components/aibridge/worker.php';
        $result = $this->worker($queue, new IdempotencyService(self::$modx))->runOnceSupervised(
            'supervisor-child-test',
            [PHP_BINARY, $workerScript]
        );

        self::assertNotNull($result);
        self::assertSame(JobState::COMPLETED, $result['status'] ?? null);

        $job = $queue->get($jobId);
        self::assertNotNull($job);
        self::assertSame(JobState::COMPLETED, $job->status());
        $resultJson = json_decode((string) ($job->raw()['result_json'] ?? '{}'), true);
        self::assertSame((int) $resource->get('id'), $resultJson['data']['resource_id'] ?? null);
    }

    private function worker(QueueManager $queue, IdempotencyService $idempotency): Worker
    {
        $registry = new JobRegistry();
        $registry->register('resource_execution', new ResourceExecutionJobHandler(self::$modx));
        return new Worker($queue, $registry, new AuditService(self::$modx), idempotency: $idempotency);
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
