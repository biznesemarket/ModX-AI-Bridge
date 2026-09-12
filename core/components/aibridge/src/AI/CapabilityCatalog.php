<?php
declare(strict_types=1);
namespace AIBridge\AI;

final class CapabilityCatalog
{
    public function all(): array { return [
        ['id'=>'site.discovery','type'=>'read','scope'=>'site:read','status'=>'available'],
        ['id'=>'site.fingerprint','type'=>'read','scope'=>'site:read','status'=>'available'],
        ['id'=>'content.contract','type'=>'read','scope'=>'site:read','status'=>'available'],
        ['id'=>'content.validation','type'=>'validate','scope'=>'content:validate','status'=>'available'],
        ['id'=>'resource.preview','type'=>'execute','scope'=>'resource:preview','status'=>'available'],
        ['id'=>'resource.write','type'=>'execute','scope'=>'resource:write','status'=>'guarded'],
        ['id'=>'resource.delete','type'=>'execute','scope'=>'resource:delete','status'=>'disabled-by-default'],
        ['id'=>'resource.publish','type'=>'execute','scope'=>'resource:publish','status'=>'approval-gated'],
    ]; }
}
