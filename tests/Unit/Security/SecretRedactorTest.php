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

    public function testFreeTextSecretsAreRedacted(): void
    {
        $redactor = new SecretRedactor();

        $bearer = $redactor->redactText('Request failed with Authorization: Bearer abcdef123456');
        $this->assertStringNotContainsString('abcdef123456', $bearer);
        $this->assertStringContainsString('[REDACTED]', $bearer);

        $bare = $redactor->redactText('upstream rejected Bearer zzz999888777');
        $this->assertStringNotContainsString('zzz999888777', $bare);
        $this->assertStringContainsString('Bearer [REDACTED]', $bare);

        $assignment = $redactor->redactText('token=ghijkl7890, password=hunter2; user=alice');
        $this->assertStringNotContainsString('ghijkl7890', $assignment);
        $this->assertStringNotContainsString('hunter2', $assignment);
        $this->assertStringContainsString('user=alice', $assignment);
    }
}
