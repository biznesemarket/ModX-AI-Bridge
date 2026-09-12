<?php

declare(strict_types=1);

namespace AIBridge\Tests\Unit\Workflow;

use AIBridge\Configuration\BridgeConfig;
use AIBridge\Security\Authorization;
use AIBridge\Security\IpAllowlist;
use AIBridge\Security\SecurityDecisionPipeline;
use AIBridge\Services\PolicyService;
use AIBridge\Workflow\ChangeState;
use PHPUnit\Framework\TestCase;

final class ApprovalWorkflowSecurityTest extends TestCase
{
    private function pipeline(): SecurityDecisionPipeline
    {
        $config = new BridgeConfig([
            'ip_allowlist' => ['127.0.0.1'],
            'allow_resource_delete' => false,
            'allow_setting_write' => false,
            'approval_required_for_publish' => true,
            'blocked_operations' => [],
        ]);
        return new SecurityDecisionPipeline($config, new Authorization(), new IpAllowlist(), new PolicyService($config));
    }

    public function testPublishWithoutApprovalReferenceIsDenied(): void
    {
        $decision = $this->pipeline()->decide(
            ['ip' => '127.0.0.1'],
            ['id' => 't1', 'scopes' => ['resource:publish']],
            'resource.publish'
        );
        self::assertFalse($decision->allowed());
        self::assertSame('approval_required', $decision->code());
    }

    public function testPublishApprovalRequiresDatabaseBackedBinding(): void
    {
        // A pipeline without a modX connection cannot prove the approval record
        // is bound to the change and must deny instead of assuming validity.
        $decision = $this->pipeline()->decide(
            ['ip' => '127.0.0.1', 'approval_id' => 1, 'change_id' => 2],
            ['id' => 't1', 'scopes' => ['resource:publish']],
            'resource.publish'
        );
        self::assertFalse($decision->allowed());
        self::assertSame('approval_invalid', $decision->code());
    }

    public function testRejectedChangeCannotBeExecuted(): void
    {
        self::assertFalse(ChangeState::canTransition(ChangeState::REJECTED, ChangeState::APPROVED));
        self::assertFalse(ChangeState::canTransition(ChangeState::REJECTED, ChangeState::EXECUTING));
    }

    public function testDraftCannotJumpToExecutingOrCompleted(): void
    {
        self::assertFalse(ChangeState::canTransition(ChangeState::DRAFT, ChangeState::EXECUTING));
        self::assertFalse(ChangeState::canTransition(ChangeState::DRAFT, ChangeState::COMPLETED));
    }

    public function testTerminalStatesHaveNoOutgoingTransitions(): void
    {
        foreach ([ChangeState::REJECTED, ChangeState::COMPLETED, ChangeState::CANCELLED] as $terminal) {
            self::assertTrue(ChangeState::terminal($terminal));
            self::assertFalse(ChangeState::canTransition($terminal, ChangeState::APPROVED));
            self::assertFalse(ChangeState::canTransition($terminal, ChangeState::EXECUTING));
        }
    }
}
