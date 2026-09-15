<?php

declare(strict_types=1);

namespace AIBridge\Tests\Integration;

use AIBridge\Audit\AuditService;
use AIBridge\Manager\OperationsConsoleService;
use AIBridge\MultiSite\ProfileService;
use AIBridge\Queue\JobRegistry;
use AIBridge\Queue\QueueManager;
use AIBridge\Queue\ResourceExecutionJobHandler;
use AIBridge\Queue\Worker;
use AIBridge\Workflow\ChangeState;
use PHPUnit\Framework\TestCase;

/**
 * Manager end-to-end certification over the Operations Console:
 * ResourceOperationProcessor -> queue/execution -> verification, with
 * WorkflowProcessor approval decisions and the console read models
 * (changes/approvals/jobs/audit) reflecting the same cycle.
 */
final class ManagerConsoleE2ETest extends TestCase
{
    private static ?\MODX\Revolution\modX $modx = null;
    private static int $profileId = 0;
    private static int $templateId = 0;
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

        self::$modx = new class extends \MODX\Revolution\modX {
            public bool $allowConsole = true;

            public function hasPermission($pm)
            {
                return $this->allowConsole;
            }
        };
        self::$modx->initialize('mgr');
        self::$modx->error = new \MODX\Revolution\Error\modError(self::$modx);

        $namespace = self::$modx->getObject(\MODX\Revolution\modNamespace::class, ['name' => 'aibridge']);
        if (!$namespace) {
            self::markTestSkipped('AIBridge Extra is not installed.');
        }
        $componentPath = rtrim((string) \MODX\Revolution\modNamespace::translatePath(self::$modx, (string) $namespace->get('path')), '/');
        require_once $componentPath . '/bootstrap.php';
        require_once $componentPath . '/processors/manager/resource-operation.class.php';
        require_once $componentPath . '/processors/manager/workflow.class.php';

        self::setting('aibridge_ip_allowlist', '["127.0.0.1"]');
        self::setting('aibridge_require_https', '0');
        self::setting('aibridge_rate_limit_per_minute', '1000');

        self::$prefix = 'manager-console-' . bin2hex(random_bytes(4));
        self::$templateId = self::template();
        self::$profileId = (int) ((new ProfileService(self::$modx))->create([
            'name' => self::$prefix,
            'site_key' => self::$prefix,
        ])->toArray()['id'] ?? 0);
    }

    protected function setUp(): void
    {
        self::$modx->allowConsole = true;
        self::$modx->user = self::authenticatedUser();
    }

    public function testManagerMutationLifecycleIsVisibleInConsoleReadModels(): void
    {
        $resourceId = $this->createResource(['pagetitle' => self::$prefix . ' before']);
        $after = self::$prefix . ' after';

        $result = $this->resourceOperation([
            'operation' => 'resource.update',
            'profile_id' => self::$profileId,
            'input' => [
                'id' => $resourceId,
                'pagetitle' => $after,
                'content' => '<h1>Manager console</h1><p>manager e2e body</p>',
            ],
        ]);

        self::assertTrue((bool) ($result['success'] ?? false), json_encode($result));
        $object = $result['object'] ?? [];
        self::assertSame(ChangeState::EXECUTING, $object['status'] ?? null);
        $changeId = (int) ($object['change_id'] ?? 0);
        $jobId = (int) ($object['job_id'] ?? 0);
        self::assertGreaterThan(0, $changeId, 'Manager mutation must create a change request.');
        self::assertGreaterThan(0, $jobId, 'Manager mutation must go through the queue.');

        $job = $this->processJob($jobId);
        self::assertSame('completed', $job['status'] ?? null, (string) ($job['error_json'] ?? ''));

        $resource = self::$modx->getObject(\MODX\Revolution\modResource::class, $resourceId);
        self::assertSame($after, (string) $resource->get('pagetitle'));

        $console = new OperationsConsoleService(self::$modx);

        $change = $this->findById($console->changes(200), $changeId);
        self::assertNotNull($change, 'Console must project the change request.');
        self::assertSame(ChangeState::COMPLETED, $change['status']);
        self::assertSame($jobId, $change['job_id']);
        self::assertSame(self::$profileId, $change['profile_id']);
        self::assertSame($after, $change['input']['pagetitle'] ?? null);
        self::assertSame($after, $change['after']['pagetitle'] ?? null);
        self::assertArrayNotHasKey('input_json', $change);
        self::assertArrayNotHasKey('after_json', $change);

        $jobRow = $this->findById($console->jobs(200), $jobId);
        self::assertNotNull($jobRow, 'Console must project the executed job.');
        self::assertSame('completed', $jobRow['status']);
        self::assertSame(self::$profileId, $jobRow['profile_id']);

        $created = $this->consoleAudit($console, 'change_created', $changeId);
        self::assertNotNull($created, 'Console audit must contain change_created.');
        self::assertSame('resource.update', $created['operation']);
        self::assertSame($resourceId, $created['resource_id']);
        self::assertNotNull($this->consoleAudit($console, 'change_execution_dispatched', $changeId));

        $execution = $this->consoleAuditForResource($console, 'resource_execution', $resourceId);
        self::assertNotNull($execution, 'Console audit must contain resource_execution for the resource.');
        self::assertSame('resource.update', $execution['operation']);
        self::assertSame($resourceId, $execution['resource_id']);
    }

    public function testWorkflowProcessorListsConsoleProjections(): void
    {
        $resourceId = $this->createResource();
        $result = $this->resourceOperation([
            'operation' => 'resource.update',
            'profile_id' => self::$profileId,
            'input' => ['id' => $resourceId, 'pagetitle' => self::$prefix . ' listed'],
        ]);
        $changeId = (int) ($result['object']['change_id'] ?? 0);
        self::assertGreaterThan(0, $changeId);

        $list = $this->workflow(['mode' => 'list', 'profile_id' => self::$profileId]);
        self::assertTrue((bool) ($list['success'] ?? false), json_encode($list));
        $changes = $list['object']['changes'] ?? [];
        self::assertNotNull($this->findById($changes, $changeId), 'Workflow list must delegate to the console projections.');
        $change = $this->findById($changes, $changeId);
        self::assertArrayHasKey('input', $change);
        self::assertArrayNotHasKey('input_json', $change);
        self::assertArrayHasKey('approvals', $list['object'] ?? []);
    }

    public function testManagerPublishRequiresApprovalAndExecutesThroughWorkflowProcessor(): void
    {
        $resourceId = $this->createResource(['published' => 0]);

        $dispatch = $this->resourceOperation([
            'operation' => 'resource.publish',
            'profile_id' => self::$profileId,
            'input' => ['id' => $resourceId],
        ]);
        self::assertTrue((bool) ($dispatch['success'] ?? false), json_encode($dispatch));
        $object = $dispatch['object'] ?? [];
        self::assertSame(ChangeState::PENDING_APPROVAL, $object['status'] ?? null);
        self::assertArrayNotHasKey('job_id', $object, 'Dangerous operations must not queue before approval.');
        $changeId = (int) ($object['change_id'] ?? 0);
        $approvalId = (int) ($object['approval_id'] ?? 0);
        self::assertGreaterThan(0, $changeId);
        self::assertGreaterThan(0, $approvalId);

        // Fail closed: the pending publish cannot be executed and is not applied.
        $denied = $this->workflow(['mode' => 'execute', 'change_id' => $changeId, 'profile_id' => self::$profileId]);
        self::assertFalse((bool) ($denied['success'] ?? true), 'Pending publish must not execute.');
        self::assertSame('workflow_error', $denied['object']['code'] ?? null);
        $resource = self::$modx->getObject(\MODX\Revolution\modResource::class, $resourceId);
        self::assertSame(0, (int) $resource->get('published'));

        $console = new OperationsConsoleService(self::$modx);
        $change = $this->findById($console->changes(200), $changeId);
        self::assertSame(ChangeState::PENDING_APPROVAL, $change['status']);
        $approval = $this->findById($console->approvals(200), $approvalId);
        self::assertNotNull($approval, 'Console must surface the pending approval.');
        self::assertSame($changeId, $approval['change_id']);
        self::assertSame('pending', $approval['status']);

        self::assertTrue((bool) ($this->workflow([
            'mode' => 'approve_decision',
            'approval_id' => $approvalId,
            'profile_id' => self::$profileId,
            'comment' => 'reviewed',
        ])['success'] ?? false));
        self::assertTrue((bool) ($this->workflow([
            'mode' => 'approve',
            'change_id' => $changeId,
            'approval_id' => $approvalId,
            'profile_id' => self::$profileId,
        ])['success'] ?? false));

        $executed = $this->workflow(['mode' => 'execute', 'change_id' => $changeId, 'profile_id' => self::$profileId]);
        self::assertTrue((bool) ($executed['success'] ?? false), json_encode($executed));
        $jobId = (int) ($executed['object']['job_id'] ?? 0);
        self::assertGreaterThan(0, $jobId);

        $job = $this->processJob($jobId);
        self::assertSame('completed', $job['status'] ?? null, (string) ($job['error_json'] ?? ''));

        $resource = self::$modx->getObject(\MODX\Revolution\modResource::class, $resourceId);
        self::assertSame(1, (int) $resource->get('published'));

        self::assertSame(ChangeState::COMPLETED, $this->findById($console->changes(200), $changeId)['status']);
        self::assertSame('approved', $this->findById($console->approvals(200), $approvalId)['status']);
    }

    public function testManagerPreviewIsSynchronousAndQueueFree(): void
    {
        $resourceId = $this->createResource(['pagetitle' => self::$prefix . ' preview']);

        $result = $this->resourceOperation([
            'operation' => 'resource.preview',
            'profile_id' => self::$profileId,
            'sync' => '1',
            'input' => ['id' => $resourceId, 'content' => '<p>manager preview body</p>'],
        ]);

        self::assertTrue((bool) ($result['success'] ?? false), json_encode($result));
        self::assertFalse((bool) ($result['object']['data']['persisted'] ?? true));
        self::assertSame('<p>manager preview body</p>', (string) ($result['object']['data']['content'] ?? ''));

        $resource = self::$modx->getObject(\MODX\Revolution\modResource::class, $resourceId);
        self::assertSame(self::$prefix . ' preview', (string) $resource->get('pagetitle'));
    }

    public function testManagerOperationAndWorkflowProcessorsRejectMissingProfileOrPermission(): void
    {
        self::assertSame('profile_required', $this->resourceOperation([
            'operation' => 'resource.update',
            'input' => ['id' => 1],
        ])['object']['code'] ?? null);

        self::assertSame('profile_not_found', $this->resourceOperation([
            'operation' => 'resource.update',
            'profile_id' => 99999999,
            'input' => ['id' => 1],
        ])['object']['code'] ?? null);

        self::assertSame('operation_not_allowed', $this->resourceOperation([
            'operation' => 'resource.rollback',
            'profile_id' => self::$profileId,
            'input' => ['id' => 1],
        ])['object']['code'] ?? null);

        foreach (['AIBridge\\Processors\\Manager\\ResourceOperationProcessor', 'AIBridge\\Processors\\Manager\\WorkflowProcessor'] as $class) {
            self::$modx->allowConsole = false;
            self::$modx->user = self::authenticatedUser();
            $denied = (new $class(self::$modx, []))->process();
            self::assertSame('manager_permission_required', $denied['object']['code'] ?? null, $class);

            self::$modx->allowConsole = true;
            self::$modx->user = null;
            $unauthenticated = (new $class(self::$modx, []))->process();
            self::assertSame('manager_auth_required', $unauthenticated['object']['code'] ?? null, $class);

            self::$modx->allowConsole = true;
            self::$modx->user = self::authenticatedUser();
        }
    }

    private function resourceOperation(array $properties): array
    {
        return (new \AIBridge\Processors\Manager\ResourceOperationProcessor(self::$modx, $properties))->process();
    }

    private function workflow(array $properties): array
    {
        return (new \AIBridge\Processors\Manager\WorkflowProcessor(self::$modx, $properties))->process();
    }

    /** @return array<string,mixed>|null */
    private function findById(array $rows, int $id): ?array
    {
        foreach ($rows as $row) {
            if ((int) ($row['id'] ?? 0) === $id) {
                return $row;
            }
        }
        return null;
    }

    /** @return array<string,mixed>|null */
    private function consoleAudit(OperationsConsoleService $console, string $event, int $changeId): ?array
    {
        foreach ($console->audit(500) as $row) {
            if (($row['event'] ?? '') !== $event) {
                continue;
            }
            if ((int) ($row['context']['change_id'] ?? 0) === $changeId) {
                return $row;
            }
        }
        return null;
    }

    /** @return array<string,mixed>|null */
    private function consoleAuditForResource(OperationsConsoleService $console, string $event, int $resourceId): ?array
    {
        foreach ($console->audit(500) as $row) {
            if (($row['event'] ?? '') === $event && (int) ($row['resource_id'] ?? 0) === $resourceId) {
                return $row;
            }
        }
        return null;
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
            $worker->runOnce('manager-console-e2e-worker');
        }

        $job = $queue->get($jobId);
        return $job !== null ? $job->raw() : [];
    }

    private static function authenticatedUser(): object
    {
        return new class {
            public function isAuthenticated(string $context = 'web'): bool
            {
                return $context === 'mgr';
            }

            public function get(string $key)
            {
                return $key === 'id' ? 1 : null;
            }
        };
    }

    private function createResource(array $overrides = []): int
    {
        $resource = self::$modx->newObject(\MODX\Revolution\modResource::class);
        $resource->fromArray(array_merge([
            'pagetitle' => self::$prefix . ' resource',
            'alias' => 'manager-console-' . bin2hex(random_bytes(5)),
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
            throw new \RuntimeException('Failed to create the manager console E2E resource.');
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
        $template = self::$modx->getObject(\MODX\Revolution\modTemplate::class, ['templatename' => 'AIBridge Manager Console E2E']);
        if (!$template) {
            $template = self::$modx->newObject(\MODX\Revolution\modTemplate::class);
            $template->fromArray([
                'templatename' => 'AIBridge Manager Console E2E',
                'content' => '[[*content]]',
                'createdon' => time(),
                'editedon' => time(),
            ]);
            $template->save();
        }
        return (int) $template->get('id');
    }
}
