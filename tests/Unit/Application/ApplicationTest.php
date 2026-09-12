<?php

declare(strict_types=1);

namespace AIBridge\Tests\Unit\Application;

use AIBridge\Security\Authorization;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    public function testScopeMappingCoversEveryExposedOperation(): void
    {
        $auth = new Authorization();
        self::assertTrue($auth->allows(['id' => 't1', 'scopes' => ['site:read']], 'site.schema', []));
        self::assertTrue($auth->allows(['id' => 't1', 'scopes' => ['site:read']], 'site.fingerprint', []));
        self::assertTrue($auth->allows(['id' => 't1', 'scopes' => ['content:validate']], 'content.validate', []));
        self::assertTrue($auth->allows(['id' => 't1', 'scopes' => ['resource:preview']], 'resource.preview', []));
        self::assertTrue($auth->allows(['id' => 't1', 'scopes' => ['resource:write']], 'resource.create', []));
        self::assertTrue($auth->allows(['id' => 't1', 'scopes' => ['resource:write']], 'resource.update', []));
        self::assertTrue($auth->allows(['id' => 't1', 'scopes' => ['resource:delete']], 'resource.delete', []));
        self::assertTrue($auth->allows(['id' => 't1', 'scopes' => ['resource:publish']], 'resource.publish', []));
    }

    public function testMissingScopeDeniesOperation(): void
    {
        $auth = new Authorization();
        self::assertFalse($auth->allows(['id' => 't1', 'scopes' => ['resource:write']], 'resource.delete', []));
        self::assertFalse($auth->allows(['id' => 't1', 'scopes' => []], 'resource.create', []));
    }

    public function testUnknownOperationIsDeniedEvenWithWildcardScope(): void
    {
        $auth = new Authorization();
        self::assertFalse($auth->allows(['id' => 't1', 'scopes' => ['*']], 'unknown.operation', []));
    }

    public function testManagerChannelRequiresExplicitManagerAuthorization(): void
    {
        $auth = new Authorization();
        self::assertTrue($auth->allows(['type' => 'manager', 'manager_authorized' => true, 'scopes' => []], 'resource.delete', []));
        self::assertFalse($auth->allows(['type' => 'manager', 'manager_authorized' => false, 'scopes' => []], 'resource.delete', []));
    }
}
