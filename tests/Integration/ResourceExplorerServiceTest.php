<?php

declare(strict_types=1);

namespace AIBridge\Tests\Integration;

use AIBridge\Manager\ResourceExplorerService;
use PHPUnit\Framework\TestCase;

final class ResourceExplorerServiceTest extends TestCase
{
    private static \MODX\Revolution\modX $modx;
    private static int $templateId = 0;
    private static string $prefix = '';

    public static function setUpBeforeClass(): void
    {
        $root = getenv('MODX_ROOT');
        if (!$root) {
            self::markTestSkipped('MODX_ROOT is not configured.');
        }

        $root = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        require_once $root . 'config.core.php';
        require_once MODX_CORE_PATH . 'vendor/autoload.php';

        self::$modx = new \MODX\Revolution\modX();
        self::$modx->initialize('mgr');

        $namespace = self::$modx->getObject(\MODX\Revolution\modNamespace::class, ['name' => 'aibridge']);
        if (!$namespace) {
            self::markTestSkipped('AIBridge Extra is not installed.');
        }
        $namespacePath = \MODX\Revolution\modNamespace::translatePath(self::$modx, (string) $namespace->get('path'));
        require_once rtrim((string) $namespacePath, '/') . '/bootstrap.php';

        self::$templateId = self::template();
        self::$prefix = 'explorer-' . bin2hex(random_bytes(4));
    }

    public function testSearchReturnsOnlyMatchingResources(): void
    {
        $service = new ResourceExplorerService(self::$modx);
        $noise = $service->search('no-such-' . bin2hex(random_bytes(6)));
        self::assertSame([], $noise, 'Search must not OR-match every resource.');

        $id = self::createResource(self::$prefix . ' match', 0, 1);
        $results = $service->search(self::$prefix);
        self::assertContains($id, array_column($results, 'id'));
        foreach ($results as $row) {
            self::assertStringContainsString(self::$prefix, (string) $row['pagetitle'] . (string) $row['alias']);
        }
    }

    public function testSearchAppliesLimitAndTemplateFilter(): void
    {
        $service = new ResourceExplorerService(self::$modx);
        for ($i = 0; $i < 3; $i++) {
            self::createResource(self::$prefix . ' limit ' . $i, 0, $i);
        }

        self::assertCount(2, $service->search(self::$prefix . ' limit', null, '', '', 2));
        self::assertCount(3, $service->search(self::$prefix . ' limit', self::$templateId, '', '', 10));
        self::assertSame([], $service->search('', null, 'no_such_tv_' . bin2hex(random_bytes(4))));
    }

    public function testTreeAppliesLimitAndSortsByMenuindex(): void
    {
        $service = new ResourceExplorerService(self::$modx);
        $parentId = self::createResource(self::$prefix . ' parent', 0, 0);
        self::createResource(self::$prefix . ' child c', $parentId, 3);
        self::createResource(self::$prefix . ' child a', $parentId, 1);
        self::createResource(self::$prefix . ' child b', $parentId, 2);

        $tree = $service->tree($parentId, 2, 0);
        self::assertCount(2, $tree);
        self::assertSame([1, 2], array_column($tree, 'menuindex'));
    }

    public function testTreeDepthExpandsNestedChildrenAndHidesDeletedSubtrees(): void
    {
        $service = new ResourceExplorerService(self::$modx);
        $root = self::createResource(self::$prefix . ' depth root', 0, 0);
        $child = self::createResource(self::$prefix . ' depth child', $root, 1);
        $grandchild = self::createResource(self::$prefix . ' depth grandchild', $child, 1);
        $greatGrandchild = self::createResource(self::$prefix . ' depth great', $grandchild, 1);
        self::createResource(self::$prefix . ' depth greatgreat', $greatGrandchild, 1);

        $flat = $service->tree($root, 50, 0);
        self::assertCount(1, $flat);
        self::assertSame($child, (int) $flat[0]['id']);
        self::assertTrue((bool) $flat[0]['has_children']);
        self::assertArrayNotHasKey('children', $flat[0], 'Depth 0 is a flat list.');

        $oneLevel = $service->tree($root, 50, 1);
        self::assertCount(1, $oneLevel);
        $childNode = $oneLevel[0]['children'][0] ?? null;
        self::assertNotNull($childNode);
        self::assertSame($grandchild, (int) $childNode['id']);
        self::assertArrayNotHasKey('children', $childNode, 'Depth 1 adds exactly one child level.');

        $twoLevels = $service->tree($root, 50, 2);
        self::assertCount(1, $twoLevels);
        $childNode = $twoLevels[0]['children'][0] ?? null;
        self::assertNotNull($childNode);
        $grandchildNode = $childNode['children'][0] ?? null;
        self::assertNotNull($grandchildNode);
        self::assertSame($greatGrandchild, (int) $grandchildNode['id']);
        self::assertArrayNotHasKey('children', $grandchildNode, 'Depth 2 stops after two nested child levels.');

        $childObject = self::$modx->getObject(\MODX\Revolution\modResource::class, $child);
        self::assertNotNull($childObject);
        $childObject->set('deleted', 1);
        $childObject->save();

        self::assertSame([], $service->tree($root, 50, 2), 'A soft-deleted child hides its whole subtree.');
    }

    public function testGetReturnsDetailsAndTemplateVariables(): void
    {
        $service = new ResourceExplorerService(self::$modx);
        $id = self::createResource(self::$prefix . ' get', 0, 4);
        $tvName = 'explorer_tv_' . bin2hex(random_bytes(4));
        self::templateVariable($tvName);

        $resource = self::$modx->getObject(\MODX\Revolution\modResource::class, ['id' => $id]);
        self::assertNotNull($resource);
        self::assertTrue($resource->setTVValue($tvName, 'explorer-tv-value'));

        $data = $service->get($id);
        self::assertNotNull($data);
        self::assertSame($id, $data['id']);
        self::assertArrayHasKey('content', $data);
        self::assertSame('explorer-tv-value', $data['tvs'][$tvName] ?? null);

        self::assertNull($service->get(99999999));
        $resource->set('deleted', 1);
        $resource->save();
        self::assertNull($service->get($id));
    }

    public function testContractAndQa(): void
    {
        $service = new ResourceExplorerService(self::$modx);
        $id = self::createResource(self::$prefix . ' qa', 0, 5);

        $contract = $service->contract($id);
        self::assertIsArray($contract);
        self::assertArrayHasKey('fields', $contract);

        $qa = $service->qa($id, ['pagetitle' => 'QA title', 'content' => '<h1>QA</h1>']);
        self::assertIsArray($qa);
        self::assertArrayHasKey('valid', $qa);
        self::assertArrayHasKey('metrics', $qa);

        self::assertNull($service->contract(99999999));
        self::assertNull($service->qa(99999999));
    }

    public function testFingerprintDiffReportsChanges(): void
    {
        $service = new ResourceExplorerService(self::$modx);
        $fromId = self::fingerprint(['site' => ['name' => 'A'], 'count' => 1]);
        $toId = self::fingerprint(['site' => ['name' => 'B'], 'count' => 1, 'extra' => true]);

        $diff = $service->fingerprintDiff($fromId, $toId);
        self::assertTrue($diff['valid'] ?? false);
        $paths = array_column($diff['changes'], 'path');
        self::assertContains('site.name', $paths);
        self::assertContains('extra', $paths);

        $missing = $service->fingerprintDiff($fromId, 99999999);
        self::assertFalse($missing['valid'] ?? true);
        self::assertSame('fingerprint_not_found', $missing['error'] ?? null);

        $bad = $service->fingerprintDiff($fromId, self::fingerprintRaw('not-json'));
        self::assertFalse($bad['valid'] ?? true);
        self::assertSame('invalid_fingerprint_snapshot', $bad['error'] ?? null);
    }

    private static function templateVariable(string $name): int
    {
        $tv = self::$modx->getObject(\MODX\Revolution\modTemplateVar::class, ['name' => $name]);
        if (!$tv) {
            $tv = self::$modx->newObject(\MODX\Revolution\modTemplateVar::class);
            $tv->fromArray(['name' => $name, 'caption' => 'Explorer TV', 'type' => 'text', 'default_text' => '']);
            $tv->save();
        }
        $link = self::$modx->getObject(\MODX\Revolution\modTemplateVarTemplate::class, [
            'tmplvarid' => (int) $tv->get('id'),
            'templateid' => self::$templateId,
        ]);
        if (!$link) {
            $link = self::$modx->newObject(\MODX\Revolution\modTemplateVarTemplate::class);
            $link->set('tmplvarid', (int) $tv->get('id'));
            $link->set('templateid', self::$templateId);
            $link->set('rank', 0);
            $link->save();
        }
        self::$modx->getCacheManager()->refresh();
        return (int) $tv->get('id');
    }

    private static function fingerprint(array $snapshot): int
    {
        return self::fingerprintRaw((string) json_encode($snapshot));
    }

    private static function fingerprintRaw(string $json): int
    {
        $fingerprint = self::$modx->newObject(\AIBridge\Model\Fingerprint::class);
        $fingerprint->fromArray([
            'profile_id' => 1,
            'algorithm' => 'sha256',
            'fingerprint' => str_repeat('a', 64),
            'snapshot_json' => $json,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $fingerprint->save();
        return (int) $fingerprint->get('id');
    }

    private static function createResource(string $title, int $parent, int $menuindex): int
    {
        $alias = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $title)) . '-' . bin2hex(random_bytes(3));
        $resource = self::$modx->newObject(\MODX\Revolution\modResource::class);
        $resource->fromArray([
            'pagetitle' => $title,
            'alias' => $alias,
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
        $template = self::$modx->getObject(\MODX\Revolution\modTemplate::class, ['templatename' => 'AIBridge Explorer Test']);
        if (!$template) {
            $template = self::$modx->newObject(\MODX\Revolution\modTemplate::class);
            $template->set('templatename', 'AIBridge Explorer Test');
            $template->set('content', '');
            $template->save();
        }
        return (int) $template->get('id');
    }
}
