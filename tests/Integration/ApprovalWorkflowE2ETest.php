<?php

declare(strict_types=1);

namespace AIBridge\Tests\Integration;

use AIBridge\Audit\AuditService;
use AIBridge\Execution\ResourceExecutionService;
use AIBridge\Model\AuditEvent;
use AIBridge\Model\ChangeRequest;
use AIBridge\MultiSite\ProfileService;
use AIBridge\Queue\JobRegistry;
use AIBridge\Queue\QueueManager;
use AIBridge\Queue\ResourceExecutionJobHandler;
use AIBridge\Queue\Worker;
use AIBridge\Workflow\ApprovalService;
use AIBridge\Workflow\ChangeExecutionService;
use AIBridge\Workflow\ChangeRequestService;
use AIBridge\Workflow\ChangeState;
use PHPUnit\Framework\TestCase;

/**
 * Runtime certification of the human-in-the-loop approval workflow:
 * draft -> submit -> approve/reject -> execute -> verify -> audit, with
 * approval/change binding, terminal-state guards and fail-closed publish.
 */
final class ApprovalWorkflowE2ETest extends TestCase
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
            'name' => 'ApprovalE2E ' . $suffix,
            'site_key' => 'approval-e2e-' . $suffix,
        ])->toArray()['id'] ?? 0);
    }

    public function testDraftSubmitRejectIsTerminalAndAudited(): void
    {
        $changes = new ChangeRequestService(self::$modx);
        $changeId = $this->newChange($changes, 'resource.update', ['id' => $this->createResource(), 'pagetitle' => 'draft title']);

        self::assertSame(ChangeState::DRAFT, $this->changeStatus($changes, $changeId));
        self::assertSame(1, $this->auditCount('change_created', $changeId));

        $submitted = $changes->submit($changeId, $this->manager());
        self::assertSame(ChangeState::PENDING_APPROVAL, (string) $submitted['status']);
        self::assertSame(1, $this->auditCount('change_submitted', $changeId));

        $rejected = $changes->reject($changeId, $this->manager(), 'not acceptable');
        self::assertSame(ChangeState::REJECTED, (string) $rejected['status']);
        self::assertSame('not acceptable', (string) $rejected['rejection_reason']);
        self::assertSame(1, $this->auditCount('change_rejected', $changeId));
        self::assertTrue(ChangeState::terminal(ChangeState::REJECTED));

        $this->assertThrowsMessage(
            fn () => (new ChangeExecutionService(self::$modx))->dispatchApproved($changeId, $this->manager()),
            'Only approved changes may execute.'
        );
        $this->assertThrowsMessage(
            fn () => $changes->reject($changeId, $this->manager(), 'again'),
            'Change is not pending approval.'
        );
    }

    public function testApprovalIsBoundToExactlyOneChange(): void
    {
        $changes = new ChangeRequestService(self::$modx);
        $approvals = new ApprovalService(self::$modx);
        $manager = $this->manager();

        $changeA = $this->newChange($changes, 'resource.update', ['id' => $this->createResource(), 'pagetitle' => 'A']);
        $changeB = $this->newChange($changes, 'resource.update', ['id' => $this->createResource(), 'pagetitle' => 'B']);
        $changes->submit($changeA, $manager);
        $changes->submit($changeB, $manager);

        $approval = $approvals->create($changeA, $manager);
        $approvalId = (int) $approval['id'];
        $approvals->decide($approvalId, 'approved', $manager);

        $this->assertThrowsMessage(
            fn () => $changes->approve($changeB, $approvalId, $manager),
            'Approval does not authorize this change.'
        );
        self::assertSame(ChangeState::PENDING_APPROVAL, $this->changeStatus($changes, $changeB));

        $approved = $changes->approve($changeA, $approvalId, $manager);
        self::assertSame(ChangeState::APPROVED, (string) $approved['status']);
        self::assertSame($approvalId, (int) $approved['approval_id']);
        self::assertSame(1, $this->auditCount('change_approved', $changeA));
    }

    public function testExecutionAcceptsOnlyApprovedChanges(): void
    {
        $changes = new ChangeRequestService(self::$modx);
        $resourceId = $this->createResource();

        $draft = $this->newChange($changes, 'resource.update', ['id' => $resourceId, 'pagetitle' => 'guarded']);
        $this->assertThrowsMessage(
            fn () => (new ChangeExecutionService(self::$modx))->dispatchApproved($draft, $this->manager()),
            'Only approved changes may execute.'
        );

        $changes->submit($draft, $this->manager());
        $this->assertThrowsMessage(
            fn () => (new ChangeExecutionService(self::$modx))->dispatchApproved($draft, $this->manager()),
            'Only approved changes may execute.'
        );
    }

    public function testApproveExecuteVerifyAndAuditHappyPath(): void
    {
        $changes = new ChangeRequestService(self::$modx);
        $approvals = new ApprovalService(self::$modx);
        $manager = $this->manager();

        $resourceId = $this->createResource();
        $resource = self::$modx->getObject(\MODX\Revolution\modResource::class, $resourceId);
        $resource->set('pagetitle', 'Approval workflow before');
        $resource->save();

        $changeId = $this->newChange($changes, 'resource.update', [
            'id' => $resourceId,
            'pagetitle' => 'Approval workflow after',
            'content' => '<h1>Approved</h1><p>body</p>',
        ]);
        $changes->submit($changeId, $manager);

        $approval = $approvals->create($changeId, $manager);
        $approvalId = (int) $approval['id'];
        $approvals->decide($approvalId, 'approved', $manager, 'looks good');
        $changes->approve($changeId, $approvalId, $manager);

        $dispatch = (new ChangeExecutionService(self::$modx))->dispatchApproved($changeId, $manager);
        $jobId = (int) ($dispatch['job_id'] ?? 0);
        self::assertGreaterThan(0, $jobId);
        self::assertSame(ChangeState::EXECUTING, (string) $this->changeStatus($changes, $changeId));

        $job = $this->processJob($jobId);
        self::assertSame('completed', $job['status'] ?? null, (string) ($job['error_json'] ?? ''));

        $resource = self::$modx->getObject(\MODX\Revolution\modResource::class, $resourceId);
        self::assertSame('Approval workflow after', (string) $resource->get('pagetitle'));
        self::assertSame(ChangeState::COMPLETED, $this->changeStatus($changes, $changeId));

        self::assertSame(1, $this->auditCount('change_created', $changeId));
        self::assertSame(1, $this->auditCount('change_submitted', $changeId));
        self::assertSame(1, $this->auditCount('change_approved', $changeId));
        self::assertSame(1, $this->auditCount('change_execution_dispatched', $changeId));

        // Terminal: a completed change cannot be approved or executed again.
        $this->assertThrowsMessage(
            fn () => (new ChangeExecutionService(self::$modx))->dispatchApproved($changeId, $manager),
            'Only approved changes may execute.'
        );
        $this->assertThrowsMessage(
            fn () => $changes->approve($changeId, $approvalId, $manager),
            'Change is not pending approval.'
        );
    }

    public function testApprovalDecisionLifecycleAndGuards(): void
    {
        $changes = new ChangeRequestService(self::$modx);
        $approvals = new ApprovalService(self::$modx);
        $manager = $this->manager();

        $changeId = $this->newChange($changes, 'resource.update', ['id' => $this->createResource(), 'pagetitle' => 'decision']);
        $changes->submit($changeId, $manager);
        $approvalId = (int) $approvals->create($changeId, $manager)['id'];

        $this->assertThrowsMessage(
            fn () => $approvals->decide($approvalId, 'maybe', $manager),
            'Invalid approval decision.'
        );

        $decided = $approvals->decide($approvalId, 'rejected', $manager, 'no');
        self::assertSame('rejected', (string) $decided['status']);

        $this->assertThrowsMessage(
            fn () => $approvals->decide($approvalId, 'approved', $manager),
            'Approval is not pending.'
        );
        $this->assertThrowsMessage(
            fn () => $changes->approve($changeId, $approvalId, $manager),
            'Approval does not authorize this change.'
        );
        self::assertSame(ChangeState::PENDING_APPROVAL, $this->changeStatus($changes, $changeId));
    }

    public function testManagerPublishWithoutApprovalIsDenied(): void
    {
        $resourceId = $this->createResource(['published' => 0]);

        $result = (new ResourceExecutionService(self::$modx))->publish(
            ['id' => $resourceId],
            $this->manager(),
            ['channel' => 'manager', 'ip' => 'manager', 'request_id' => bin2hex(random_bytes(16)), 'idempotency_key' => 'publish-guard-' . bin2hex(random_bytes(8))]
        );

        self::assertFalse((bool) ($result['success'] ?? false), json_encode($result));
        self::assertSame('approval_required', $result['error']['code'] ?? null);

        $resource = self::$modx->getObject(\MODX\Revolution\modResource::class, $resourceId);
        self::assertSame(0, (int) $resource->get('published'));
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

    private function newChange(ChangeRequestService $changes, string $operation, array $input): int
    {
        $change = $changes->create($operation, $input, $this->manager(), ['request_id' => bin2hex(random_bytes(16))]);
        return (int) $change['id'];
    }

    private function changeStatus(ChangeRequestService $changes, int $changeId): string
    {
        $change = $changes->get($changeId);
        self::assertNotNull($change);
        return (string) $change['status'];
    }

    private function auditCount(string $event, int $changeId): int
    {
        $count = 0;
        $rows = self::$modx->getCollection(AuditEvent::class, ['event' => $event]);
        foreach ($rows ?: [] as $row) {
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

    private function createResource(array $overrides = []): int
    {
        $resource = self::$modx->newObject(\MODX\Revolution\modResource::class);
        $resource->fromArray(array_merge([
            'pagetitle' => 'Approval E2E ' . bin2hex(random_bytes(4)),
            'alias' => 'approval-e2e-' . bin2hex(random_bytes(5)),
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
            throw new \RuntimeException('Failed to create the approval E2E resource.');
        }
        return (int) $resource->get('id');
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
            $worker->runOnce('approval-e2e-worker');
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
        $template = self::$modx->getObject(\MODX\Revolution\modTemplate::class, ['templatename' => 'AIBridgeApprovalE2E']);
        if (!$template) {
            $template = self::$modx->newObject(\MODX\Revolution\modTemplate::class);
            $template->fromArray([
                'templatename' => 'AIBridgeApprovalE2E',
                'content' => '[[*content]]',
                'createdon' => time(),
                'editedon' => time(),
            ]);
            $template->save();
        }
        return (int) $template->get('id');
    }
}
