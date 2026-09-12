<?php

declare(strict_types=1);

namespace AIBridge\Tests\Unit\Security;

use AIBridge\Security\Authorization;
use PHPUnit\Framework\TestCase;

final class AuthorizationTest extends TestCase
{
    public function testReadScopeAllowsSchema(): void
    {
        $this->assertTrue((new Authorization())->allows(['scopes'=>['site:read']], 'site.schema'));
    }

    public function testReadScopeDoesNotAllowWrite(): void
    {
        $this->assertFalse((new Authorization())->allows(['scopes'=>['site:read']], 'resource.update'));
    }

    public function testWildcardAllowsKnownOperation(): void
    {
        $this->assertTrue((new Authorization())->allows(['scopes'=>['*']], 'resource.update'));
    }
}
