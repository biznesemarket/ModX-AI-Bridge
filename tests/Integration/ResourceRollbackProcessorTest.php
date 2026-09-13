<?php

declare(strict_types=1);

namespace AIBridge\Tests\Integration;

use AIBridge\Processors\ResourceRollbackProcessor;
use AIBridge\Services\SnapshotService;
use PHPUnit\Framework\TestCase;

final class ResourceRollbackProcessorTest extends TestCase
{
    private static ?\MODX\Revolution\modX $modx = null;
    private static int $templateId = 0;

    public static function setUpBeforeClass(): void
    {
        $root = getenv('MODX_ROOT');
        if (!$root) {
            self::markTestSkipped('MODX_ROOT is not configured.');
        }

        $root = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        require_once $root . 'config.core.php';
        require_once MODX_CORE_PATH . 'vendor/autoload.php';

        self::$modx = new class extends \MODX\Revolution\modX {
            public bool $allowConsole = true;

            public function hasPermission($pm)
            {
                return $this->allowConsole;
            }
        };
        self::$modx->initialize('mgr');
        self::$modx->error = new \MODX\Revolution\Error\modError(self::$modx);

        $namespace = self::$modx->getObject(\MODX\Revolution\modNamespace::class, ['name' => 'aibridge']);
        if (!$namespace) {
            self::markTestSkipped('AIBridge Extra is not installed.');
        }
        $namespacePath = \MODX\Revolution\modNamespace::translatePath(self::$modx, (string) $namespace->get('path'));
        require_once rtrim((string) $namespacePath, '/') . '/bootstrap.php';

        self::$templateId = self::template();
    }

    public function testDeniesUnauthenticatedManager(): void
    {
        self::$modx->allowConsole = true;
        self::$modx->user = null;

        $result = $this->processor(['snapshot_id' => 1])->process();
        self::assertFalse((bool) $result['success']);
        self::assertSame('manager_auth_required', $result['object']['code'] ?? null);
    }

    public function testDeniesManagerWithoutPermission(): void
    {
        self::$modx->allowConsole = false;
        self::$modx->user = self::authenticatedUser();

        $result = $this->processor(['snapshot_id' => 1])->process();
        self::assertFalse((bool) $result['success']);
        self::assertSame('manager_permission_required', $result['object']['code'] ?? null);
    }

    public function testRequiresSnapshotId(): void
    {
        self::$modx->allowConsole = true;
        self::$modx->user = self::authenticatedUser();

        $result = $this->processor([])->process();
        self::assertFalse((bool) $result['success']);
        self::assertSame('invalid_snapshot', $result['object']['code'] ?? null);
        self::assertStringContainsString('snapshot_id', (string) $result['message']);
    }

    public function testUnknownSnapshotReturnsFailureInsteadOfThrowing(): void
    {
        self::$modx->allowConsole = true;
        self::$modx->user = self::authenticatedUser();

        $result = $this->processor(['snapshot_id' => 99999999])->process();
        self::assertFalse((bool) $result['success']);
        self::assertSame('rollback_failed', $result['object']['code'] ?? null);
    }

    public function testRollbackRestoresResourceAndRecordsAudit(): void
    {
        self::$modx->allowConsole = true;
        self::$modx->user = self::authenticatedUser();

        $resource = self::$modx->newObject(\MODX\Revolution\modResource::class);
        $resource->fromArray([
            'pagetitle' => 'Rollback processor ' . bin2hex(random_bytes(4)),
            'alias' => 'rollback-processor-' . bin2hex(random_bytes(4)),
            'template' => self::$templateId,
            'content' => '<h1>Snapshot original</h1>',
            'published' => 0,
            'deleted' => 0,
            'context_key' => 'web',
            'createdon' => time(),
            'editedon' => time(),
        ]);
        self::assertTrue($resource->save());
        $id = (int) $resource->get('id');
        $original = (string) $resource->get('pagetitle');

        $snapshot = (new SnapshotService(self::$modx))->createForResource($resource, 'resource.update', 0);
        $snapshotId = (int) $snapshot['id'];
        self::assertGreaterThan(0, $snapshotId);

        $resource->set('pagetitle', 'mutated ' . $original);
        self::assertTrue($resource->save());

        $result = $this->processor(['snapshot_id' => $snapshotId, 'profile_id' => 0])->process();
        self::assertTrue((bool) $result['success'], json_encode($result));

        $restored = self::$modx->getObject(\MODX\Revolution\modResource::class, $id);
        self::assertNotNull($restored);
        self::assertSame($original, (string) $restored->get('pagetitle'));
        self::assertGreaterThanOrEqual(
            1,
            self::$modx->getCount(\AIBridge\Model\AuditEvent::class, ['resource_id' => $id, 'event' => 'resource_rollback'])
        );
    }

    private function processor(array $properties = []): ResourceRollbackProcessor
    {
        return new ResourceRollbackProcessor(self::$modx, $properties);
    }

    private static function authenticatedUser(): object
    {
        return new class {
            public function isAuthenticated(string $context = 'web'): bool
            {
                return $context === 'mgr';
            }

            public function get(string $key)
            {
                return $key === 'id' ? 1 : null;
            }
        };
    }

    private static function template(): int
    {
        $template = self::$modx->getObject(\MODX\Revolution\modTemplate::class, ['templatename' => 'AIBridge Rollback Test']);
        if (!$template) {
            $template = self::$modx->newObject(\MODX\Revolution\modTemplate::class);
            $template->set('templatename', 'AIBridge Rollback Test');
            $template->set('content', '');
            $template->save();
        }
        return (int) $template->get('id');
    }
}
