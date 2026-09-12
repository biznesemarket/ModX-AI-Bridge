<?php

declare(strict_types=1);
namespace AIBridge\Middleware;
use AIBridge\Security\RateLimiter;
use AIBridge\Configuration\BridgeConfig;
final class RateLimitMiddleware
{
    public function __construct(private readonly RateLimiter $limiter, private readonly BridgeConfig $config) {}
    public function process(array $request): array { $limit=(int)$this->config->get('rate_limit_per_minute',60); $key=(string)($request['principal']['id']??$request['ip']??'anonymous'); $r=$this->limiter->consume($key,$limit,60); if (!$r['allowed']) throw new \RuntimeException('Rate limit exceeded'); return $request+['rate_limit'=>$r]; }
}
