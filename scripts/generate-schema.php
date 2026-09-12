<?php

declare(strict_types=1);

/**
 * Generate the MODX 3 / xPDO 3 model from the Extra schema.
 *
 * In the reproducible integration environment the authoritative xPDO CLI is
 * the one bundled with the installed MODX core. This avoids generating maps
 * against a different xPDO version than the runtime.
 */

$root = dirname(__DIR__);
$modxRoot = rtrim((string)(getenv('MODX_ROOT') ?: '/var/www/html'), '/');
$schema = $root . '/core/components/aibridge/schema/aibridge.mysql.schema.xml';
$destination = $root . '/core/components/aibridge/src/';
$xpdo = $modxRoot . '/core/vendor/bin/xpdo';
$configFile = __DIR__ . '/xpdo.properties.inc.php';

foreach ([$schema, $xpdo, $configFile] as $path) {
    if (!is_file($path)) {
        throw new RuntimeException('Required file not found: ' . $path);
    }
}

if (!is_dir($destination) && !mkdir($destination, 0775, true) && !is_dir($destination)) {
    throw new RuntimeException('Unable to create model destination: ' . $destination);
}

$command = implode(' ', [
    escapeshellarg(PHP_BINARY),
    escapeshellarg($xpdo),
    'parse-schema',
    '--config=' . escapeshellarg($configFile),
    '--psr4=' . escapeshellarg('AIBridge\\'),
    '--update=1',
    'mysql',
    escapeshellarg($schema),
    escapeshellarg($destination),
]);

passthru($command, $exitCode);
if ($exitCode !== 0) {
    throw new RuntimeException('xPDO schema generation failed with exit code ' . $exitCode);
}

echo "xPDO model generation: PASS\n";
echo "Schema: {$schema}\n";
echo "Destination: {$destination}\n";
