<?php

declare(strict_types=1);

namespace AIBridge\Tests\Unit\Execution;

use AIBridge\Configuration\BridgeConfig;
use AIBridge\Security\Authorization;
use AIBridge\Security\IpAllowlist;
use AIBridge\Security\SecurityDecisionPipeline;
use AIBridge\Services\PolicyService;
use PHPUnit\Framework\TestCase;

final class ResourceExecutionPolicyTest extends TestCase
{
    public function testDeleteIsDeniedByDefault(): void
    {
        $config = new BridgeConfig([
            'ip_allowlist' => ['127.0.0.1'],
            'allow_resource_delete' => false,
            'allow_setting_write' => false,
            'approval_required_for_publish' => true,
            'blocked_operations' => [],
        ]);
        $pipeline = new SecurityDecisionPipeline($config, new Authorization(), new IpAllowlist(), new PolicyService($config));
        $decision = $pipeline->decide(['ip'=>'127.0.0.1'], ['id'=>'t1','scopes'=>['resource:delete']], 'resource.delete');
        self::assertFalse($decision->allowed());
        self::assertSame('operation_disabled', $decision->code());
    }

    public function testPublishRequiresApproval(): void
    {
        $config = new BridgeConfig([
            'ip_allowlist' => ['127.0.0.1'],
            'allow_resource_delete' => false,
            'allow_setting_write' => false,
            'approval_required_for_publish' => true,
            'blocked_operations' => [],
        ]);
        $pipeline = new SecurityDecisionPipeline($config, new Authorization(), new IpAllowlist(), new PolicyService($config));
        $decision = $pipeline->decide(['ip'=>'127.0.0.1'], ['id'=>'t1','scopes'=>['resource:publish']], 'resource.publish');
        self::assertFalse($decision->allowed());
        self::assertSame('approval_required', $decision->code());
    }
}
