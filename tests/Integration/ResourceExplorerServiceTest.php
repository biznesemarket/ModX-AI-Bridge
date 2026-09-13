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
