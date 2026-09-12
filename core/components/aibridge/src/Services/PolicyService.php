<?php

declare(strict_types=1);
namespace AIBridge\Services;
use AIBridge\Configuration\BridgeConfig;
final class PolicyService
{
    public function __construct(private readonly BridgeConfig $config) {}
    public function authorize(string $operation,array $input=[]): array
    {
        $disabled=['resource.delete'=>'allow_resource_delete','settings.write'=>'allow_setting_write'];
        if (isset($disabled[$operation]) && !$this->config->isEnabled($disabled[$operation])) return ['allowed'=>false,'code'=>'operation_disabled','reasons'=>[$operation]];
        $blocked=$this->config->get('blocked_operations',[]);
        if (is_array($blocked) && in_array($operation,$blocked,true)) return ['allowed'=>false,'code'=>'operation_blocked','reasons'=>[$operation]];
        return ['allowed'=>true,'code'=>'policy_allowed','reasons'=>[]];
    }
}
