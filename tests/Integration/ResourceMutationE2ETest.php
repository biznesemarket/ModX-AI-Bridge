<?php

declare(strict_types=1);

namespace AIBridge\Tests\Integration;

use AIBridge\Audit\AuditService;
use AIBridge\Execution\ResourceExecutionService;
use AIBridge\Manager\ResourceOperationService;
use AIBridge\Model\AuditEvent;
use AIBridge\Model\ChangeRequest;
use AIBridge\Model\Snapshot;
use AIBridge\MultiSite\ProfileService;
use AIBridge\Queue\JobContext;
use AIBridge\Queue\JobDeadline;
use AIBridge\Queue\JobRecord;
use AIBridge\Queue\JobRegistry;
use AIBridge\Queue\JobTimeoutException;
use AIBridge\Queue\QueueManager;
use AIBridge\Queue\ResourceExecutionJobHandler;
use AIBridge\Queue\Worker;
use AIBridge\Verification\RollbackService;
use AIBridge\Workflow\ApprovalService;
use AIBridge\Workflow\ChangeExecutionService;
use AIBridge\Workflow\ChangeRequestService;
use AIBridge\Workflow\ChangeState;
use PHPUnit\Framework\TestCase;

/**
 * Runtime certification for the Manager mutation cycle:
 * update / preview / delete / publish, post-execution verification,
 * controlled verification mismatch and snapshot rollback.
 */
final class ResourceMutationE2ETest extends TestCase
{
    private static ?\MODX\Revolution\modX $modx = null;
    private static int $profileId = 0;
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

        self::$templateId = self::template();

        $suffix = bin2hex(random_bytes(4));
        self::$profileId = (int) ((new ProfileService(self::$modx))->create([
            'name' => 'MutationE2E ' . $suffix,
            'site_key' => 'mutation-e2e-' . $suffix,
        ])->toArray()['id'] ?? 0);
    }

    public function testManagerUpdateRunsThroughQueueWithSnapshotVerificationAndAudit(): void
    {
        $resourceId = $this->createResource();
        $before = 'E2E before ' . bin2hex(random_bytes(3));
        $after = 'E2E after ' . bin2hex(random_bytes(3));

        $tvId = $this->templateVariable();
        $resource = self::$modx->getObject(\MODX\Revolution\modResource::class, $resourceId);
        $resource->set('pagetitle', $before);
        $resource->setTVValue($tvId, 'before tv');
        $resource->save();

        $result = (new ResourceOperationService(self::$modx))->dispatch(
            'resource.update',
            [
                'id' => $resourceId,
                'pagetitle' => $after,
                'content' => '<h1>Updated</h1><p>updated body</p>',
                'tvs' => ['tv:e2e_tv' => 'verified tv'],
            ],
            $this->manager()
        );

        self::assertTrue((bool) ($result['success'] ?? false), json_encode($result));
        self::assertSame(ChangeState::EXECUTING, $result['status'] ?? null);

        $jobId = (int) ($result['job_id'] ?? 0);
        $changeId = (int) ($result['change_id'] ?? 0);
        self::assertGreaterThan(0, $jobId);
        self::assertGreaterThan(0, $changeId);

        $job = $this->processJob($jobId);
        self::assertSame('completed', $job['status'] ?? null, (string) ($job['error_json'] ?? ''));

        $resource = self::$modx->getObject(\MODX\Revolution\modResource::class, $resourceId);
        self::assertSame($after, (string) $resource->get('pagetitle'));
        self::assertSame('<h1>Updated</h1><p>updated body</p>', (string) $resource->get('content'));
        self::assertSame('verified tv', (string) $resource->getTVValue($tvId));

        $change = self::$modx->getObject(ChangeRequest::class, $changeId);
        self::assertNotNull($change);
        self::assertSame(ChangeState::COMPLETED, (string) $change->get('status'));

        self::assertGreaterThanOrEqual(1, self::$modx->getCount(Snapshot::class, ['resource_id' => $resourceId]));
        self::assertGreaterThanOrEqual(
            1,
            self::$modx->getCount(AuditEvent::class, ['resource_id' => $resourceId, 'event' => 'resource_execution'])
        );
    }

    public function testManagerPreviewIsSynchronousAndNonPersistent(): void
    {
        $resourceId = $this->createResource();
        $result = (new ResourceOperationService(self::$modx))->dispatch(
            'resource.preview',
            ['id' => $resourceId, 'content' => '<p>preview body</p>'],
            $this->manager()
        );

        self::assertTrue((bool) ($result['success'] ?? false), json_encode($result));
        self::assertFalse((bool) ($result['data']['persisted'] ?? true));
        self::assertSame($resourceId, (int) ($result['data']['resource_id'] ?? 0));
        self::assertSame('<p>preview body</p>', (string) ($result['data']['content'] ?? ''));
    }

    public function testManagerDeleteRequiresApprovalThenExecutesAndRollbackRefusesRecreation(): void
    {
        self::setting('aibridge_allow_resource_delete', '1');
        try {
            $resourceId = $this->createResource();

            $dispatch = (new ResourceOperationService(self::$modx))->dispatch(
                'resource.delete',
                ['id' => $resourceId],
                $this->manager()
            );
            self::assertTrue((bool) ($dispatch['success'] ?? false), json_encode($dispatch));
            self::assertSame(ChangeState::PENDING_APPROVAL, $dispatch['status'] ?? null);

            $jobId = $this->approveAndExecute((int) $dispatch['change_id'], (int) $dispatch['approval_id']);
            $job = $this->processJob($jobId);
            self::assertSame('completed', $job['status'] ?? null, (string) ($job['error_json'] ?? ''));

            $change = self::$modx->getObject(ChangeRequest::class, (int) $dispatch['change_id']);
            self::assertSame(ChangeState::COMPLETED, (string) $change->get('status'));

            $resource = self::$modx->getObject(\MODX\Revolution\modResource::class, $resourceId);
            self::assertNull($resource, 'Delete must remove the resource row.');

            $result = json_decode((string) ($job['result_json'] ?? ''), true);
            $snapshotId = (int) ($result['data']['snapshot']['id'] ?? 0);
            self::assertGreaterThan(0, $snapshotId);

            $refused = false;
            try {
                (new RollbackService(self::$modx))->rollback($snapshotId, $this->manager(), [
                    'channel' => 'manager',
                    'ip' => 'manager',
                    'request_id' => bin2hex(random_bytes(8)),
                ]);
            } catch (\RuntimeException $e) {
                $refused = str_contains($e->getMessage(), 'recreation');
            }
            self::assertTrue($refused, 'Rollback must refuse automatic recreation of a deleted resource.');
        } finally {
            self::setting('aibridge_allow_resource_delete', '0');
        }
    }

    public function testManagerPublishRequiresApprovalAndVerifiesPublishedState(): void
    {
        $resourceId = $this->createResource(['published' => 0]);

        $dispatch = (new ResourceOperationService(self::$modx))->dispatch(
            'resource.publish',
            ['id' => $resourceId],
            $this->manager()
        );
        self::assertTrue((bool) ($dispatch['success'] ?? false), json_encode($dispatch));
        self::assertSame(ChangeState::PENDING_APPROVAL, $dispatch['status'] ?? null);

        $jobId = $this->approveAndExecute((int) $dispatch['change_id'], (int) $dispatch['approval_id']);
        $job = $this->processJob($jobId);
        self::assertSame('completed', $job['status'] ?? null, (string) ($job['error_json'] ?? ''));

        $resource = self::$modx->getObject(\MODX\Revolution\modResource::class, $resourceId);
        self::assertSame(1, (int) $resource->get('published'));
        self::assertNotSame('', (string) $resource->get('publishedon'));

        $change = self::$modx->getObject(ChangeRequest::class, (int) $dispatch['change_id']);
        self::assertSame(ChangeState::COMPLETED, (string) $change->get('status'));
    }

    public function testUpdateCannotBypassPublishApproval(): void
    {
        $resourceId = $this->createResource(['published' => 0]);

        $result = (new ResourceExecutionService(self::$modx))->update(
            [
                'id' => $resourceId,
                'pagetitle' => 'Attempted covert publish',
                'content' => '<h1>Attempted covert publish</h1><p>body</p>',
                'published' => 1,
            ],
            $this->manager(),
            ['channel' => 'manager', 'ip' => 'manager', 'request_id' => bin2hex(random_bytes(16)), 'idempotency_key' => 'bypass-' . bin2hex(random_bytes(8))]
        );

        self::assertTrue((bool) ($result['success'] ?? false), json_encode($result));
        $resource = self::$modx->getObject(\MODX\Revolution\modResource::class, $resourceId);
        self::assertSame(0, (int) $resource->get('published'), 'resource.update must not change publish state.');
    }

    public function testPostExecutionVerificationMismatchFailsJobWithoutRetry(): void
    {
        $resourceId = $this->createResource();

        $policy = $this->manager();
        $changes = new ChangeRequestService(self::$modx);
        $change = $changes->create(
            'resource.update',
            ['id' => $resourceId, 'pagetitle' => 'actual value'],
            $policy,
            ['request_id' => bin2hex(random_bytes(16))]
        );
        $changeId = (int) $change['id'];
        $changes->submit($changeId, $policy);
        $changes->autoApprove($changeId, $policy);

        $row = self::$modx->getObject(ChangeRequest::class, $changeId);
        $row->set('after_json', json_encode(['id' => $resourceId, 'pagetitle' => 'expected but absent'], JSON_UNESCAPED_UNICODE));
        self::assertTrue($row->save());

        $dispatch = (new ChangeExecutionService(self::$modx))->dispatchApproved($changeId, $policy);
        $job = $this->processJob((int) $dispatch['job_id']);

        self::assertSame('failed', $job['status'] ?? null);
        $error = json_decode((string) ($job['error_json'] ?? '{}'), true);
        self::assertSame('job_failed', $error['code'] ?? null);

        $row = self::$modx->getObject(ChangeRequest::class, $changeId);
        self::assertSame(ChangeState::FAILED, (string) $row->get('status'));
    }

    public function testDeadlineAbortReleasesIdempotencyKeyForRetry(): void
    {
        $resourceId = $this->createResource();
        $service = new ResourceExecutionService(self::$modx);
        $key = 'deadline-' . bin2hex(random_bytes(8));
        $payload = [
            'id' => $resourceId,
            'pagetitle' => 'Deadline retry target',
            'content' => '<h1>Deadline retry target</h1><p>body</p>',
        ];
        $request = [
            'channel' => 'manager',
            'ip' => 'manager',
            'request_id' => bin2hex(random_bytes(8)),
            'idempotency_key' => $key,
            '_deadline_at' => microtime(true) - 5,
        ];

        $aborted = $service->update($payload, $this->manager(), $request);
        self::assertSame('execution_timeout', $aborted['error']['code'] ?? null);

        $request['_deadline_at'] = microtime(true) + 60;
        $retried = $service->update($payload, $this->manager(), $request);
        self::assertTrue((bool) ($retried['success'] ?? false), json_encode($retried));
    }

    public function testHandlerFailsFastWhenDeadlineBudgetIsSpent(): void
    {
        $job = new JobRecord([
            'id' => 0,
            'type' => 'resource_execution',
            'status' => 'running',
            'attempts' => 1,
            'max_attempts' => 3,
            'timeout_seconds' => 1,
            'payload_json' => json_encode([
                'operation' => 'resource.update',
                'input' => ['id' => 1],
                'principal' => $this->manager(),
                'request' => [],
            ], JSON_UNESCAPED_UNICODE),
            'request_id' => bin2hex(random_bytes(8)),
            'profile_id' => self::$profileId,
        ]);
        $context = new JobContext(new QueueManager(self::$modx), $job, new JobDeadline(microtime(true) - 1));

        $this->expectException(JobTimeoutException::class);
        (new ResourceExecutionJobHandler(self::$modx))->handle($job, $context);
    }

    public function testRollbackRestoresFieldsAndTemplateVariables(): void
    {
        $tvId = $this->templateVariable();
        $resourceId = $this->createResource();
        $resource = self::$modx->getObject(\MODX\Revolution\modResource::class, $resourceId);
        $resource->setTVValue($tvId, 'initial tv value');
        $resource->save();

        $original = 'Rollback original ' . bin2hex(random_bytes(3));
        $resource->set('pagetitle', $original);
        $resource->save();

        $result = (new ResourceExecutionService(self::$modx))->update(
            ['id' => $resourceId, 'pagetitle' => 'mutated title', 'tvs' => ['tv:e2e_tv' => 'mutated tv value']],
            $this->manager(),
            ['channel' => 'manager', 'ip' => 'manager', 'request_id' => bin2hex(random_bytes(16)), 'idempotency_key' => 'rollback-' . bin2hex(random_bytes(8))]
        );
        self::assertTrue((bool) ($result['success'] ?? false), json_encode($result));
        $snapshotId = (int) ($result['data']['snapshot']['id'] ?? 0);
        self::assertGreaterThan(0, $snapshotId);

        $resource = self::$modx->getObject(\MODX\Revolution\modResource::class, $resourceId);
        self::assertSame('mutated title', (string) $resource->get('pagetitle'));

        $rollback = (new RollbackService(self::$modx))->rollback($snapshotId, $this->manager(), [
            'channel' => 'manager',
            'ip' => 'manager',
            'request_id' => bin2hex(random_bytes(8)),
        ]);
        self::assertTrue((bool) ($rollback['success'] ?? false), json_encode($rollback));

        $resource = self::$modx->getObject(\MODX\Revolution\modResource::class, $resourceId);
        self::assertSame($original, (string) $resource->get('pagetitle'));
        self::assertSame('initial tv value', (string) $resource->getTVValue($tvId));
    }

    /** @return array<string,mixed> */
    private function manager(): array
    {
        return [
            'id' => '1',
            'type' => 'manager',
            'scopes' => ['*'],
            'manager_authorized' => true,
            'profile_id' => self::$profileId,
        ];
    }

    private function approveAndExecute(int $changeId, int $approvalId): int
    {
        $manager = $this->manager();
        (new ApprovalService(self::$modx))->decide($approvalId, 'approved', $manager);
        (new ChangeRequestService(self::$modx))->approve($changeId, $approvalId, $manager);

        $dispatch = (new ChangeExecutionService(self::$modx))->dispatchApproved($changeId, $manager);
        return (int) ($dispatch['job_id'] ?? 0);
    }

    private function createResource(array $overrides = []): int
    {
        $resource = self::$modx->newObject(\MODX\Revolution\modResource::class);
        $resource->fromArray(array_merge([
            'pagetitle' => 'E2E ' . bin2hex(random_bytes(4)),
            'alias' => 'e2e-' . bin2hex(random_bytes(5)),
            'template' => self::$templateId,
            'content' => '<h1>Initial</h1><p>initial body</p>',
            'published' => 0,
            'deleted' => 0,
            'hidemenu' => 1,
            'context_key' => 'web',
            'createdon' => time(),
            'editedon' => time(),
        ], $overrides));
        if (!$resource->save()) {
            throw new \RuntimeException('Failed to create the E2E resource.');
        }
        return (int) $resource->get('id');
    }

    /**
     * Process a single job by id using the real worker, returning the job row.
     *
     * @return array<string,mixed>
     */
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
            $worker->runOnce('mutation-e2e-worker');
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
        $template = self::$modx->getObject(\MODX\Revolution\modTemplate::class, ['templatename' => 'AIBridgeMutationE2E']);
        if (!$template) {
            $template = self::$modx->newObject(\MODX\Revolution\modTemplate::class);
            $template->fromArray([
                'templatename' => 'AIBridgeMutationE2E',
                'content' => '[[*content]]',
                'createdon' => time(),
                'editedon' => time(),
            ]);
            $template->save();
        }
        return (int) $template->get('id');
    }

    private function templateVariable(): int
    {
        $tv = self::$modx->getObject(\MODX\Revolution\modTemplateVar::class, ['name' => 'e2e_tv']);
        if (!$tv) {
            $tv = self::$modx->newObject(\MODX\Revolution\modTemplateVar::class);
            $tv->fromArray(['name' => 'e2e_tv', 'caption' => 'E2E TV', 'type' => 'text', 'default_text' => '']);
            $tv->save();
        }
        $link = self::$modx->getObject(\MODX\Revolution\modTemplateVarTemplate::class, [
            'tmplvarid' => (int) $tv->get('id'),
            'templateid' => self::$templateId,
        ]);
        if (!$link) {
            $link = self::$modx->newObject(\MODX\Revolution\modTemplateVarTemplate::class);
            $link->set('tmplvarid', (int) $tv->get('id'));
            $link->set('templateid', self::$templateId);
            $link->set('rank', 0);
            $link->save();
        }
        self::$modx->getCacheManager()->refresh();
        return (int) $tv->get('id');
    }
}
