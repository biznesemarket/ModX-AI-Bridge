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

/*
 * xPDO's "--update" path can rewrite existing platform classes with unresolved
 * template markers. Generated platform classes are derived artefacts, so they
 * are always recreated from scratch to keep generation deterministic.
 */
$platformDir = $destination . 'Model/mysql';
foreach (glob($platformDir . '/*.php') ?: [] as $stale) {
    if (!unlink($stale)) {
        throw new RuntimeException('Unable to remove stale generated class: ' . $stale);
    }
}
if (is_file($destination . 'Model/metadata.mysql.php') && !unlink($destination . 'Model/metadata.mysql.php')) {
    throw new RuntimeException('Unable to remove stale generated metadata.');
}

$command = implode(' ', [
    escapeshellarg(PHP_BINARY),
    escapeshellarg($xpdo),
    'parse-schema',
    '--config=' . escapeshellarg($configFile),
    '--psr4=' . escapeshellarg('AIBridge\\'),
    'mysql',
    escapeshellarg($schema),
    escapeshellarg($destination),
]);

passthru($command, $exitCode);
if ($exitCode !== 0) {
    throw new RuntimeException('xPDO schema generation failed with exit code ' . $exitCode);
}

foreach (glob($platformDir . '/*.php') ?: [] as $generated) {
    $contents = (string) file_get_contents($generated);
    if (str_contains($contents, '[+') || !str_contains($contents, '<?php')) {
        throw new RuntimeException('Generated class is invalid (unresolved template markers): ' . $generated);
    }
}
$metadata = $destination . 'Model/metadata.mysql.php';
if (!is_file($metadata) || str_contains((string) file_get_contents($metadata), '[+')) {
    throw new RuntimeException('Generated metadata.mysql.php is missing or invalid.');
}

echo "xPDO model generation: PASS\n";
echo "Schema: {$schema}\n";
echo "Destination: {$destination}\n";
