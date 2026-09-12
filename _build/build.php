<?php

declare(strict_types=1);

use MODX\Revolution\modX;
use MODX\Revolution\modSystemSetting;
use MODX\Revolution\Transport\modPackageBuilder;
use xPDO\Transport\xPDOTransport;

set_time_limit(0);

$config = require __DIR__ . '/config.inc.php';
$modxRoot = $config['modx_root'];

if (!is_file($modxRoot . 'config.core.php')) {
    fwrite(STDERR, "MODX_ROOT does not point to a MODX installation: {$modxRoot}\n");
    exit(2);
}

require_once $modxRoot . 'config.core.php';
require_once MODX_CORE_PATH . 'vendor/autoload.php';

$modx = new modX();
$modx->initialize('mgr');
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget(XPDO_CLI_MODE ? 'ECHO' : 'HTML');

$root = dirname(__DIR__) . DIRECTORY_SEPARATOR;
$sources = [
    'root' => $root,
    'build' => __DIR__ . DIRECTORY_SEPARATOR,
    'core' => $root . 'core/components/aibridge',
    'assets' => $root . 'assets/components/aibridge',
];

$builder = new modPackageBuilder($modx);
$builder->createPackage(
    $config['name_lower'],
    $config['version'],
    $config['release']
);

$builder->registerNamespace(
    $config['name_lower'],
    false,
    true,
    '{core_path}components/aibridge/'
);

/*
 * MODX 3 menu definition.
 * menus.php returns declarative modMenu data; the builder creates the object.
 */
$menus = require __DIR__ . '/elements/menus.php';

foreach ($menus as $text => $data) {
    $menu = $modx->newObject('modMenu');
    $menu->fromArray([
        'text' => $text,
        'description' => $data['description'] ?? '',
        'action' => $data['action'] ?? '',
        'parent' => $data['parent'] ?? 'components',
        'menuindex' => $data['menuindex'] ?? 0,
        'params' => $data['params'] ?? '',
        'handler' => $data['handler'] ?? '',
    ]);

    $builder->putVehicle($builder->createVehicle($menu, [
        xPDOTransport::PRESERVE_KEYS => true,
        xPDOTransport::UPDATE_OBJECT => true,
        xPDOTransport::UNIQUE_KEY => ['text', 'parent'],
    ]));
}

/*
 * System settings.
 */
$settings = require __DIR__ . '/elements/settings.php';

foreach ($settings as $setting) {
    if (!$setting instanceof modSystemSetting) {
        continue;
    }

    $vehicle = $builder->createVehicle($setting, [
        xPDOTransport::PRESERVE_KEYS => true,
        xPDOTransport::UPDATE_OBJECT => true,
        xPDOTransport::UNIQUE_KEY => 'key',
    ]);

    $builder->putVehicle($vehicle);
}

/*
 * Files are installed as package files. Database/model initialization belongs
 * to the PHP resolver and runs only after files have been copied.
 */
$coreVehicle = $builder->createVehicle($modx->newObject('modSystemSetting'), [
    xPDOTransport::PRESERVE_KEYS => true,
]);

$coreVehicle->resolve('file', [
    'source' => $sources['core'],
    'target' => "return MODX_CORE_PATH . 'components/';",
]);

$coreVehicle->resolve('file', [
    'source' => $sources['assets'],
    'target' => "return MODX_ASSETS_PATH . 'components/';",
]);

$coreVehicle->resolve('php', [
    'source' => __DIR__ . '/resolvers/install-model.resolver.php',
]);

$builder->putVehicle($coreVehicle);

$builder->setPackageAttributes([
    'license' => file_get_contents($sources['root'] . 'LICENSE'),
    'readme' => file_get_contents($sources['root'] . 'README.md'),
]);

$builder->pack();

$package = MODX_CORE_PATH
    . 'packages/'
    . $config['name_lower'] . '-'
    . $config['version'] . '-'
    . $config['release']
    . '.transport.zip';

fwrite(STDOUT, "Built: {$package}\n");

if ($config['install'] === true) {
    fwrite(STDOUT, "Auto-install requested. Use Manager package installation for the skeleton until the CI installer is verified.\n");
}
