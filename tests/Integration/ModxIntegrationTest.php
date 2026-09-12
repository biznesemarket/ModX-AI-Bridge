<?php

declare(strict_types=1);

namespace AIBridge\Tests\Integration;

use AIBridge\Application\Application;
use AIBridge\Model\Token;
use PHPUnit\Framework\TestCase;

final class ModxIntegrationTest extends TestCase
{
    private static ?\MODX\Revolution\modX $modx = null;

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

        $version = self::$modx->getVersionData();
        if (version_compare((string)$version['full_version'], '3.2.0', '<')) {
            self::markTestSkipped('MODX 3.2+ is required.');
        }

        $namespace = self::$modx->getObject(\MODX\Revolution\modNamespace::class, ['name' => 'aibridge']);
        if (!$namespace) {
            self::markTestSkipped('AIBridge Extra is not installed.');
        }

        $namespacePath = \MODX\Revolution\modNamespace::translatePath(self::$modx, (string) $namespace->get('path'));
        require_once rtrim((string) $namespacePath, '/') . '/bootstrap.php';
    }

    public function testModelPackageLoads(): void
    {
        self::assertNotNull(self::$modx);
        self::assertTrue(class_exists(Token::class));
        self::assertNotSame('', self::$modx->getTableName(Token::class));
        self::assertNotNull(self::$modx->newObject(Token::class));
    }

    public function testApplicationServiceIsRegistered(): void
    {
        self::assertTrue(self::$modx->services->has('aibridge.application'));
        self::assertInstanceOf(Application::class, self::$modx->services->get('aibridge.application'));
    }

    public function testManagerMenuExists(): void
    {
        $menu = self::$modx->getObject(\MODX\Revolution\modMenu::class, ['text' => 'aibridge']);
        self::assertNotNull($menu);
        self::assertSame('home', $menu->get('action'));
        self::assertSame('aibridge', $menu->get('namespace'));
    }

    public function testCustomProcessorRuns(): void
    {
        $response = self::$modx->runProcessor(
            'health',
            [],
            ['processors_path' => MODX_CORE_PATH . 'components/aibridge/processors/']
        );

        self::assertFalse($response->isError(), $response->getMessage());
        self::assertSame('ok', $response->response['object']['status'] ?? null);
    }
}
