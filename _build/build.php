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

/**
 * Deterministic vehicle identifier for reproducible transport packages.
 *
 * xPDO derives the vehicle file name from md5(class . '_' . guid) and generates a
 * random guid with md5(uniqid(rand(), true)) when none is supplied. Passing a
 * stable guid keeps vehicle paths, signatures and the manifest reproducible.
 */
function aibridge_vehicle_guid(string $kind, string $identity): string
{
    return md5('modx-ai-bridge/' . $kind . '/' . $identity);
}

/**
 * Rewrite a transport zip with sorted entries and a fixed modification time so
 * identical package contents produce identical bytes.
 *
 * xPDO embeds build time in every entry header; SOURCE_DATE_EPOCH (default
 * 1980-01-01 UTC, the minimum DOS timestamp) is used instead.
 */
function aibridge_normalize_transport_zip(string $path, int $epoch): void
{
    if (!is_file($path)) {
        throw new RuntimeException('Transport package not found for normalization: ' . $path);
    }
    $archive = new ZipArchive();
    if ($archive->open($path) !== true) {
        throw new RuntimeException('Unable to open transport package for normalization: ' . $path);
    }
    $entries = [];
    for ($index = 0; $index < $archive->numFiles; $index++) {
        $name = (string) $archive->getNameIndex($index);
        $entries[$name] = (string) $archive->getFromIndex($index);
    }
    $archive->close();
    ksort($entries, SORT_STRING);

    $temporary = $path . '.normalize.tmp';
    if (is_file($temporary)) {
        unlink($temporary);
    }
    $normalized = new ZipArchive();
    if ($normalized->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create a normalized transport package.');
    }
    if (!method_exists($normalized, 'setMtimeName')) {
        throw new RuntimeException('ZipArchive::setMtimeName is required (PHP 8.0+).');
    }
    $timezone = date_default_timezone_get();
    date_default_timezone_set('UTC');
    try {
        foreach ($entries as $name => $content) {
            if (str_ends_with($name, '/')) {
                $normalized->addEmptyDir($name);
            } else {
                $normalized->addFromString($name, $content);
            }
            if ($normalized->setMtimeName($name, $epoch) === false) {
                throw new RuntimeException('Unable to normalize the modification time of ' . $name);
            }
        }
        $normalized->setArchiveComment('');
        $normalized->close();
    } finally {
        date_default_timezone_set($timezone);
    }
    if (!rename($temporary, $path)) {
        throw new RuntimeException('Unable to replace the transport package with its normalized copy.');
    }
}

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
    false,
    '{core_path}components/aibridge/'
);

/*
 * The namespace vehicle is created here instead of via registerNamespace() so its
 * guid (and therefore its vehicle path and signature) is deterministic. xPDO
 * derives the path from md5(class . '_' . guid) and randomizes the guid otherwise.
 */
$builder->putVehicle($builder->createVehicle($builder->{'namespace'}, [
    xPDOTransport::UNIQUE_KEY => 'name',
    xPDOTransport::PRESERVE_KEYS => true,
    xPDOTransport::UPDATE_OBJECT => true,
    xPDOTransport::RESOLVE_FILES => true,
    xPDOTransport::RESOLVE_PHP => true,
    'guid' => aibridge_vehicle_guid('namespace', $config['name_lower']),
]));

/*
 * MODX 3 menu definition.
 * menus.php returns declarative modMenu data; the builder creates the object.
 */
$menus = require __DIR__ . '/elements/menus.php';

foreach ($menus as $text => $data) {
    $menu = $modx->newObject(\MODX\Revolution\modMenu::class);
    $menu->fromArray([
        'text' => $text,
        'description' => $data['description'] ?? '',
        'action' => $data['action'] ?? '',
        'parent' => $data['parent'] ?? 'components',
        'menuindex' => $data['menuindex'] ?? 0,
        'params' => $data['params'] ?? '',
        'handler' => $data['handler'] ?? '',
        'namespace' => 'aibridge',
    ], '', true);

    $builder->putVehicle($builder->createVehicle($menu, [
        xPDOTransport::PRESERVE_KEYS => true,
        xPDOTransport::UPDATE_OBJECT => true,
        xPDOTransport::UNIQUE_KEY => ['text', 'parent'],
        'guid' => aibridge_vehicle_guid('menu', $text . ':' . ($data['parent'] ?? 'components')),
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
        'guid' => aibridge_vehicle_guid('setting', (string) $setting->get('key')),
    ]);

    $builder->putVehicle($vehicle);
}

/*
 * Files are installed as package files. Database/model initialization belongs
 * to the PHP resolver and runs only after files have been copied.
 */
$coreVehicle = $builder->createVehicle($modx->newObject('modSystemSetting'), [
    xPDOTransport::PRESERVE_KEYS => true,
    'guid' => aibridge_vehicle_guid('core', 'files'),
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

$coreVehicle->resolve('php', [
    'source' => __DIR__ . '/resolvers/register-bootstrap.resolver.php',
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
    . $config['version']
    . ($config['release'] !== '' ? '-' . $config['release'] : '')
    . '.transport.zip';

$sourceDateEpoch = (int) (getenv('SOURCE_DATE_EPOCH') ?: 315532800);
aibridge_normalize_transport_zip($package, $sourceDateEpoch);

fwrite(STDOUT, "Built: {$package}\n");

if ($config['install'] === true) {
    fwrite(STDOUT, "Auto-install requested. Use Manager package installation for the skeleton until the CI installer is verified.\n");
}
