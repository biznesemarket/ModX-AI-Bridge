<?php

declare(strict_types=1);

namespace AIBridge\Tests\Integration;

use AIBridge\Configuration\ConfigFactory;
use AIBridge\MultiSite\ProfileService;
use AIBridge\Security\TokenManager;
use PHPUnit\Framework\TestCase;

final class ManagerProcessorsTest extends TestCase
{
    private static ?\MODX\Revolution\modX $modx = null;
    private static string $prefix = '';
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
        $componentPath = rtrim((string) \MODX\Revolution\modNamespace::translatePath(self::$modx, (string) $namespace->get('path')), '/');
        require_once $componentPath . '/bootstrap.php';
        require_once $componentPath . '/processors/manager/resources.class.php';
        require_once $componentPath . '/processors/manager/overview.class.php';
        require_once $componentPath . '/processors/manager/action.class.php';

        self::$prefix = 'manager-' . bin2hex(random_bytes(4));
        self::$templateId = self::template();
    }

    protected function setUp(): void
    {
        self::$modx->allowConsole = true;
        self::$modx->user = self::authenticatedUser();
    }

    public function testManagerProcessorsRequireManagerPermission(): void
    {
        foreach ([
            'AIBridge\\Processors\\Manager\\ResourcesProcessor',
            'AIBridge\\Processors\\Manager\\OverviewProcessor',
            'AIBridge\\Processors\\Manager\\ActionProcessor',
        ] as $class) {
            self::$modx->allowConsole = false;
            $result = (new $class(self::$modx, []))->process();
            self::assertFalse((bool) $result['success'], $class . ' must deny without permission.');
            self::assertSame('manager_permission_required', $result['object']['code'] ?? null, $class);

            self::$modx->allowConsole = true;
            self::$modx->user = null;
            $result = (new $class(self::$modx, []))->process();
            self::assertFalse((bool) $result['success'], $class . ' must deny unauthenticated managers.');
            self::assertSame('manager_auth_required', $result['object']['code'] ?? null, $class);
            self::$modx->user = self::authenticatedUser();
        }
    }

    public function testResourcesProcessorModes(): void
    {
        $root = self::createResource(self::$prefix . ' root', 0, 0);
        $childA = self::createResource(self::$prefix . ' child a', $root, 1);
        $childB = self::createResource(self::$prefix . ' child b', $root, 2);
        $grandchild = self::createResource(self::$prefix . ' grandchild', $childA, 1);

        $tree = $this->resources(['mode' => 'tree', 'parent' => $root, 'limit' => 5, 'depth' => 1]);
        self::assertTrue((bool) $tree['success']);
        $items = $tree['object']['items'] ?? [];
        self::assertSame([$childA, $childB], array_column($items, 'id'));
        self::assertSame([1, 2], array_column($items, 'menuindex'));
        self::assertSame([$grandchild], array_column($items[0]['children'] ?? [], 'id'));
        self::assertArrayNotHasKey('children', $items[1]);

        $search = $this->resources(['mode' => 'search', 'query' => self::$prefix, 'limit' => 10]);
        self::assertTrue((bool) $search['success']);
        self::assertContains($root, array_column($search['object']['items'] ?? [], 'id'));

        $fallback = $this->resources(['mode' => 'no-such-mode', 'query' => self::$prefix, 'limit' => 10]);
        self::assertSame($search['object']['items'], $fallback['object']['items'], 'Unknown modes fall back to search.');

        $get = $this->resources(['mode' => 'get', 'id' => $root]);
        self::assertTrue((bool) $get['success']);
        self::assertSame($root, (int) ($get['object']['item']['id'] ?? 0));

        $missing = $this->resources(['mode' => 'get', 'id' => 99999999]);
        self::assertFalse((bool) $missing['success']);
        self::assertSame('not_found', $missing['object']['code'] ?? null);

        $contract = $this->resources(['mode' => 'contract', 'id' => $root]);
        self::assertTrue((bool) $contract['success']);
        self::assertArrayHasKey('fields', $contract['object']['item'] ?? []);

        $qa = $this->resources(['mode' => 'qa', 'id' => $root]);
        self::assertTrue((bool) $qa['success']);
        self::assertArrayHasKey('valid', $qa['object']['item'] ?? []);

        $diff = $this->resources(['mode' => 'fingerprint_diff', 'from_id' => 1, 'to_id' => 99999999]);
        self::assertTrue((bool) $diff['success']);
        self::assertFalse((bool) ($diff['object']['diff']['valid'] ?? true));
        self::assertSame('fingerprint_not_found', $diff['object']['diff']['error'] ?? null);
    }

    public function testOverviewProcessorReturnsBoundedConsoleSections(): void
    {
        $profiles = new ProfileService(self::$modx);
        for ($i = 0; $i < 3; $i++) {
            $profiles->create(['name' => self::$prefix . ' overview ' . $i, 'site_key' => self::$prefix . '-overview-' . $i]);
        }

        $result = $this->overview([]);
        self::assertTrue((bool) $result['success']);
        $object = $result['object'] ?? [];
        foreach (['overview', 'profiles', 'tokens', 'policies', 'jobs', 'audit', 'fingerprints'] as $section) {
            self::assertArrayHasKey($section, $object, $section . ' must be present.');
        }
        self::assertSame('ready', $object['overview']['readiness']['status'] ?? null);
        self::assertLessThanOrEqual(50, count($object['profiles']));
        self::assertGreaterThanOrEqual(3, count($object['profiles']));
        self::assertLessThanOrEqual(50, count($object['tokens']));
        self::assertLessThanOrEqual(50, count($object['jobs']));
        self::assertLessThanOrEqual(50, count($object['audit']));
    }

    public function testActionProcessorStatusTransitions(): void
    {
        $profileId = (int) ((new ProfileService(self::$modx))->create([
            'name' => self::$prefix . ' action',
            'site_key' => self::$prefix . '-action',
        ])->toArray()['id'] ?? 0);
        self::assertGreaterThan(0, $profileId);

        $missing = $this->action(['entity' => 'profile', 'id' => 0, 'status' => 'disabled']);
        self::assertFalse((bool) $missing['success']);

        $unknownEntity = $this->action(['entity' => 'bogus', 'id' => $profileId, 'status' => 'disabled']);
        self::assertFalse((bool) $unknownEntity['success']);

        $invalidStatus = $this->action(['entity' => 'profile', 'id' => $profileId, 'status' => 'bogus']);
        self::assertFalse((bool) $invalidStatus['success']);

        self::assertTrue((bool) $this->action(['entity' => 'profile', 'id' => $profileId, 'status' => 'disabled'])['success']);
        self::assertSame('disabled', (string) self::$modx->getObject(\AIBridge\Model\Profile::class, $profileId)->get('status'));
        self::assertTrue((bool) $this->action(['entity' => 'profile', 'id' => $profileId, 'status' => 'active'])['success']);

        $token = (new TokenManager(self::$modx, ConfigFactory::fromModx(self::$modx)))
            ->issue(self::$prefix . '-token', ['site:read'], $profileId);
        $tokenId = (int) $token['id'];
        self::assertTrue((bool) $this->action(['entity' => 'token', 'id' => $tokenId, 'status' => 'revoked'])['success']);
        self::assertSame('revoked', (string) self::$modx->getObject(\AIBridge\Model\Token::class, $tokenId)->get('status'));

        $policy = self::$modx->newObject(\AIBridge\Model\Policy::class);
        $policy->fromArray([
            'profile_id' => $profileId,
            'name' => self::$prefix . '-policy',
            'rules_json' => '{}',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $policy->save();
        $policyId = (int) $policy->get('id');
        self::assertTrue((bool) $this->action(['entity' => 'policy', 'id' => $policyId, 'status' => 'disabled'])['success']);
        self::assertSame('disabled', (string) self::$modx->getObject(\AIBridge\Model\Policy::class, $policyId)->get('status'));
    }

    private function resources(array $properties): array
    {
        return (new \AIBridge\Processors\Manager\ResourcesProcessor(self::$modx, $properties))->process();
    }

    private function overview(array $properties): array
    {
        return (new \AIBridge\Processors\Manager\OverviewProcessor(self::$modx, $properties))->process();
    }

    private function action(array $properties): array
    {
        return (new \AIBridge\Processors\Manager\ActionProcessor(self::$modx, $properties))->process();
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

    private static function createResource(string $title, int $parent, int $menuindex): int
    {
        $resource = self::$modx->newObject(\MODX\Revolution\modResource::class);
        $resource->fromArray([
            'pagetitle' => $title,
            'alias' => strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $title)) . '-' . bin2hex(random_bytes(3)),
            'content' => '<h1>' . $title . '</h1>',
            'published' => 1,
            'parent' => $parent,
            'template' => self::$templateId,
            'context_key' => 'web',
            'class_key' => \MODX\Revolution\modDocument::class,
            'type' => 'document',
            'content_type' => 1,
            'menuindex' => $menuindex,
            'editedon' => time(),
        ]);
        $resource->save();
        return (int) $resource->get('id');
    }

    private static function template(): int
    {
        $template = self::$modx->getObject(\MODX\Revolution\modTemplate::class, ['templatename' => 'AIBridge Manager Processors Test']);
        if (!$template) {
            $template = self::$modx->newObject(\MODX\Revolution\modTemplate::class);
            $template->set('templatename', 'AIBridge Manager Processors Test');
            $template->set('content', '[[*content]]');
            $template->save();
        }
        return (int) $template->get('id');
    }
}
