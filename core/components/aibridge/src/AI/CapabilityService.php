<?php
declare(strict_types=1);
namespace AIBridge\AI;

final class CapabilityService
{
    public function __construct(private readonly CapabilityCatalog $catalog=new CapabilityCatalog()) {}
    public function manifest(): array { return ['version'=>'1.0','product'=>'modx-ai-bridge','capabilities'=>$this->catalog->all(),'interfaces'=>['rest'=>['version'=>'v2'],'mcp'=>['protocolVersion'=>'2025-06-18']]]; }
}
