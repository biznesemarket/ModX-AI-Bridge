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

    public function testResourceReadScopeAllowsRead(): void
    {
        $this->assertTrue((new Authorization())->allows(['scopes'=>[Authorization::SCOPE_READ_RESOURCE]], 'resource.read'));
    }

    public function testSiteReadScopeDoesNotAllowResourceRead(): void
    {
        $this->assertFalse((new Authorization())->allows(['scopes'=>['site:read']], 'resource.read'));
    }

    public function testWildcardAllowsKnownOperation(): void
    {
        $this->assertTrue((new Authorization())->allows(['scopes'=>['*']], 'resource.update'));
    }

    public function testRollbackScopeAllowsRollback(): void
    {
        $this->assertTrue((new Authorization())->allows(['scopes'=>[Authorization::SCOPE_ROLLBACK]], 'resource.rollback'));
    }

    public function testWriteScopeDoesNotAllowRollback(): void
    {
        $this->assertFalse((new Authorization())->allows(['scopes'=>[Authorization::SCOPE_WRITE]], 'resource.rollback'));
    }

    public function testAuthorizedManagerAllowsRollback(): void
    {
        $principal = ['type'=>'manager','manager_authorized'=>true,'scopes'=>[]];
        $this->assertTrue((new Authorization())->allows($principal, 'resource.rollback'));
    }

    public function testUnauthorizedManagerIsDeniedRollback(): void
    {
        $principal = ['type'=>'manager','manager_authorized'=>false,'scopes'=>[]];
        $this->assertFalse((new Authorization())->allows($principal, 'resource.rollback'));
    }
}
