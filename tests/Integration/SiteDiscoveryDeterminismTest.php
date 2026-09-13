<?php

declare(strict_types=1);

namespace AIBridge\Tests\Integration;

use AIBridge\Services\SiteIntelligenceService;
use PHPUnit\Framework\TestCase;

/**
 * Site discovery must be deterministic: the inspectors order their collections
 * explicitly (name ascending), so the contract section order — and therefore
 * the fingerprint — does not depend on the storage/insertion order.
 */
final class SiteDiscoveryDeterminismTest extends TestCase
{
    private static ?\MODX\Revolution\modX $modx = null;
    private static string $suffix = '';
    /** @var list<array{0:class-string,1:int}> */
    private static array $created = [];

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

        self::$suffix = bin2hex(random_bytes(4));

        // Created in reverse alphabetical order on purpose: without the
        // explicit `sortby`, the PK order would put the "zzz" rows first.
        self::template('zzz_aibridge_det_' . self::$suffix);
        self::template('aaa_aibridge_det_' . self::$suffix);
        self::chunk('zzz_aibridge_det_' . self::$suffix);
        self::chunk('aaa_aibridge_det_' . self::$suffix);
        self::snippet('zzz_aibridge_det_' . self::$suffix);
        self::snippet('aaa_aibridge_det_' . self::$suffix);
        self::templateVariable('zzz_aibridge_det_' . self::$suffix);
        self::templateVariable('aaa_aibridge_det_' . self::$suffix);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$modx !== null) {
            foreach (self::$created as [$class, $id]) {
                $row = self::$modx->getObject($class, $id);
                if ($row) {
                    $row->remove();
                }
            }
        }
        self::$created = [];
    }

    public function testInspectorSectionsAreNameSorted(): void
    {
        $contract = (new SiteIntelligenceService(self::$modx))->discover()['contract'];

        foreach (['templates', 'chunks', 'snippets', 'template_variables'] as $section) {
            $names = array_map(
                static fn (array $row): string => (string) ($row['name'] ?? ''),
                is_array($contract[$section] ?? null) ? $contract[$section] : []
            );
            $aaa = array_search('aaa_aibridge_det_' . self::$suffix, $names, true);
            $zzz = array_search('zzz_aibridge_det_' . self::$suffix, $names, true);
            self::assertNotFalse($aaa, $section . ': aaa fixture missing.');
            self::assertNotFalse($zzz, $section . ': zzz fixture missing.');
            self::assertLessThan($zzz, $aaa, $section . ' must be returned in ascending name order.');
        }
    }

    public function testFingerprintIsStableAcrossCalls(): void
    {
        $service = new SiteIntelligenceService(self::$modx);
        $first = $service->discover()['fingerprint'];
        $second = $service->discover()['fingerprint'];
        self::assertNotSame('', $first);
        self::assertSame($first, $second);
    }

    private static function template(string $name): void
    {
        if (self::$modx->getObject(\MODX\Revolution\modTemplate::class, ['templatename' => $name])) return;
        $row = self::$modx->newObject(\MODX\Revolution\modTemplate::class);
        $row->fromArray(['templatename' => $name, 'content' => '[[*content]]', 'createdon' => time(), 'editedon' => time()]);
        $row->save();
        self::$created[] = [\MODX\Revolution\modTemplate::class, (int) $row->get('id')];
    }

    private static function chunk(string $name): void
    {
        if (self::$modx->getObject(\MODX\Revolution\modChunk::class, ['name' => $name])) return;
        $row = self::$modx->newObject(\MODX\Revolution\modChunk::class);
        $row->fromArray(['name' => $name, 'snippet' => '[[+value]]']);
        $row->save();
        self::$created[] = [\MODX\Revolution\modChunk::class, (int) $row->get('id')];
    }

    private static function snippet(string $name): void
    {
        if (self::$modx->getObject(\MODX\Revolution\modSnippet::class, ['name' => $name])) return;
        $row = self::$modx->newObject(\MODX\Revolution\modSnippet::class);
        $row->fromArray(['name' => $name, 'snippet' => 'return "";']);
        $row->save();
        self::$created[] = [\MODX\Revolution\modSnippet::class, (int) $row->get('id')];
    }

    private static function templateVariable(string $name): void
    {
        if (self::$modx->getObject(\MODX\Revolution\modTemplateVar::class, ['name' => $name])) return;
        $row = self::$modx->newObject(\MODX\Revolution\modTemplateVar::class);
        $row->fromArray(['name' => $name, 'caption' => $name, 'type' => 'text', 'default_text' => '']);
        $row->save();
        self::$created[] = [\MODX\Revolution\modTemplateVar::class, (int) $row->get('id')];
    }
}
