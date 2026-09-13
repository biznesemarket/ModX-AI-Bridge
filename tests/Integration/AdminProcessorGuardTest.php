<?php

declare(strict_types=1);

namespace AIBridge\Tests\Integration;

use AIBridge\Manager\OperationsConsoleService;
use PHPUnit\Framework\TestCase;

final class AdminProcessorGuardTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $root = getenv('MODX_ROOT');
        if (!$root) {
            self::markTestSkipped('MODX_ROOT is not configured.');
        }
        $root = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        require_once $root . 'config.core.php';
        require_once MODX_CORE_PATH . 'vendor/autoload.php';
    }

    public function testDeniesUnauthenticatedManager(): void
    {
        $modx = $this->fakeModx(false);
        $modx->user = null;
        $result = $this->probe($modx)->check();
        self::assertSame('manager_auth_required', $result['data']['code'] ?? null);
    }

    public function testDeniesAuthenticatedManagerWithoutPermission(): void
    {
        $modx = $this->fakeModx(false);
        $modx->user = $this->authenticatedUser();
        $result = $this->probe($modx)->check();
        self::assertSame('manager_permission_required', $result['data']['code'] ?? null);
    }

    public function testAllowsAuthenticatedManagerWithPermission(): void
    {
        $modx = $this->fakeModx(true);
        $modx->user = $this->authenticatedUser();
        $probe = $this->probe($modx);
        self::assertNull($probe->check());
        self::assertInstanceOf(OperationsConsoleService::class, $probe->consoleService());
    }

    private function fakeModx(bool $allow): \MODX\Revolution\modX
    {
        return new class($allow) extends \MODX\Revolution\modX {
            public function __construct(private bool $allow)
            {
                parent::__construct();
            }

            public function hasPermission($pm)
            {
                return $this->allow;
            }
        };
    }

    private function authenticatedUser(): object
    {
        return new class {
            public function isAuthenticated(string $context = 'web'): bool
            {
                return $context === 'mgr';
            }
        };
    }

    private function probe(\MODX\Revolution\modX $modx): object
    {
        return new class($modx) extends \AIBridge\Manager\AdminProcessor {
            public function process()
            {
                return null;
            }

            public function check(): ?array
            {
                return $this->requirePermission();
            }

            public function consoleService(): OperationsConsoleService
            {
                return $this->console();
            }

            public function failure($msg = '', $object = null)
            {
                return ['success' => false, 'message' => $msg, 'data' => $object];
            }
        };
    }
}
