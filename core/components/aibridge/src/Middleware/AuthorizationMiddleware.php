<?php

declare(strict_types=1);
namespace AIBridge\Middleware;
use AIBridge\Security\SecurityDecisionPipeline;
final class AuthorizationMiddleware
{
    public function __construct(private readonly SecurityDecisionPipeline $pipeline) {}
    public function process(array $request): array { $operation=(string)($request['operation']??''); $d=$this->pipeline->decide($request,$request['principal']??[],$operation); if (!$d->allowed) throw new \RuntimeException('Security decision denied: '.$d->code); return $request+['security_decision'=>$d->toArray()]; }
}
