<?php

declare(strict_types=1);
namespace AIBridge\Middleware;
use AIBridge\Security\TokenAuthenticator;
final class AuthenticationMiddleware
{
    public function __construct(private readonly TokenAuthenticator $authenticator) {}
    public function process(array $request): array { $result=$this->authenticator->authenticate((string)($request['headers']['authorization']??'')); if (!$result['authenticated']) throw new \RuntimeException('Authentication failed: '.($result['reason']??'unknown')); return $request+['principal'=>$result['principal']]; }
}
