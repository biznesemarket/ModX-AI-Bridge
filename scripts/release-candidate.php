<?php
declare(strict_types=1);

/**
 * Release Candidate builder.
 *
 * Builds the transport package, verifies its contents, computes the SHA-256 and
 * writes release metadata (dist/<package>.release.json + .sha256). Run inside
 * the MODX container:
 *
 *   MODX_ROOT=/var/www/html php scripts/release-candidate.php
 */

$root = dirname(__DIR__);
$config = require $root . '/_build/config.inc.php';
$modxRoot = rtrim((string) (getenv('MODX_ROOT') ?: '/var/www/html'), '/') . '/';
putenv('MODX_ROOT=' . $modxRoot);

$buildCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/_build/build.php');
passthru($buildCommand, $exitCode);
if ($exitCode !== 0) {
    fwrite(STDERR, "Package build failed (exit {$exitCode}).\n");
    exit(1);
}

$packageName = $config['name_lower'] . '-' . $config['version'] . '-' . $config['release'];
$packagePath = $modxRoot . 'core/packages/' . $packageName . '.transport.zip';
if (!is_file($packagePath)) {
    fwrite(STDERR, "Built package not found: {$packagePath}\n");
    exit(2);
}

$zip = new ZipArchive();
if ($zip->open($packagePath) !== true) {
    fwrite(STDERR, "Package is not a readable zip archive.\n");
    exit(3);
}
$entries = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $entries[] = (string) $zip->getNameIndex($i);
}
$zip->close();
foreach (['/aibridge/src/Application/Application.php', '/aibridge/api/index.php', '/aibridge/js/manager.js'] as $required) {
    if (!array_filter($entries, static fn(string $e): bool => str_contains($e, $required))) {
        fwrite(STDERR, "Package is missing required entry {$required}\n");
        exit(4);
    }
}

require_once $modxRoot . 'config.core.php';
require_once MODX_CORE_PATH . 'vendor/autoload.php';
$modx = new \MODX\Revolution\modX();
$modx->initialize('mgr');
$modxVersion = (string) ($modx->getVersionData()['full_version'] ?? 'unknown');

$migrationLevel = [];
$connection = $modx->getConnection();
$pdo = is_object($connection) ? ($connection->pdo ?? null) : null;
if ($pdo instanceof \PDO) {
    $tablePrefix = (string) $modx->getOption('table_prefix', null, 'modx_');
    $migrationTable = $tablePrefix . 'aibridge_migrations';
    try {
        $stmt = $pdo->query("SELECT filename FROM {$migrationTable} ORDER BY filename");
        $migrationLevel = $stmt ? $stmt->fetchAll(\PDO::FETCH_COLUMN) : [];
    } catch (\Throwable) {
        $migrationLevel = [];
    }
}

$metadata = [
    'component' => 'modx-ai-bridge',
    'version' => (string) $config['version'],
    'release' => (string) $config['release'],
    'package' => basename($packagePath),
    'sha256' => hash_file('sha256', $packagePath),
    'size_bytes' => filesize($packagePath),
    'signed' => false,
    'note' => 'No signing key is configured; integrity is provided by the recorded SHA-256.',
    'modx_version' => $modxVersion,
    'php_version' => PHP_VERSION,
    'migration_level' => array_values($migrationLevel),
    'built_at' => gmdate('c'),
];

$distDir = $root . '/dist';
if (!is_dir($distDir) && !mkdir($distDir, 0775, true) && !is_dir($distDir)) {
    fwrite(STDERR, "Unable to create dist directory.\n");
    exit(5);
}
$metadataPath = $distDir . '/' . $packageName . '.release.json';
file_put_contents($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
file_put_contents($packagePath . '.sha256', $metadata['sha256'] . '  ' . basename($packagePath) . "\n");

fwrite(STDOUT, "RELEASE CANDIDATE: {$packageName}\n");
foreach ($metadata as $key => $value) {
    $rendered = is_array($value) ? implode(', ', $value) : (is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
    fwrite(STDOUT, '  ' . $key . ': ' . $rendered . "\n");
}
fwrite(STDOUT, "  metadata: {$metadataPath}\n");
