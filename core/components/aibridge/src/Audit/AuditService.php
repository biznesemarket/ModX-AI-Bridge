<?php

declare(strict_types=1);
namespace AIBridge\Audit;
use AIBridge\Contracts\AuditLogger;
use AIBridge\Security\SecretRedactor;
use MODX\Revolution\modX;
final class AuditService implements AuditLogger
{
    public function __construct(private readonly modX $modx, private readonly SecretRedactor $redactor=new SecretRedactor()) {}
    public function record(string $event,array $context=[]): void
    {
        if (!$this->modx->getOption('aibridge.audit_enabled',null,true)) return;
        $row=$this->modx->newObject(\AIBridge\Model\AuditEvent::class);
        $context=$this->redactor->redactRecursive($context);
        $row->fromArray(['event'=>$event,'actor_type'=>(string)($context['actor_type']??'system'),'actor_id'=>(string)($context['actor_id']??''),'operation'=>(string)($context['operation']??''),'resource_id'=>isset($context['resource_id'])?(int)$context['resource_id']:null,'request_id'=>(string)($context['request_id']??''),'context_json'=>json_encode($context,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'created_at'=>date('Y-m-d H:i:s')]);
        $row->save();
    }
}
