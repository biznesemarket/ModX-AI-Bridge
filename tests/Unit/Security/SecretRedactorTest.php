<?php

declare(strict_types=1);
namespace AIBridge\Tests\Unit\Security;
use AIBridge\Security\SecretRedactor;
use PHPUnit\Framework\TestCase;
final class SecretRedactorTest extends TestCase
{
    public function testNestedSecretsAreRedacted(): void
    {
        $result=(new SecretRedactor())->redactRecursive(['authorization'=>'Bearer x','nested'=>['api_key'=>'secret','name'=>'ok']]);
        $this->assertSame('[REDACTED]',$result['authorization']);
        $this->assertSame('[REDACTED]',$result['nested']['api_key']);
        $this->assertSame('ok',$result['nested']['name']);
    }
}
