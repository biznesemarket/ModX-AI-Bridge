<?php

declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/install-package.php /absolute/path/package.transport.zip\n");
    exit(2);
}

$package = $argv[1];
$root = getenv('MODX_ROOT') ?: '/var/www/html';

require $root . '/config.core.php';
require_once MODX_CORE_PATH . 'vendor/autoload.php';

$modx = \MODX\Revolution\modX::getInstance();
$modx->initialize('mgr');

$package = realpath($package);
if ($package === false || !is_file($package)) {
    throw new RuntimeException('Transport package not found.');
}

$scan = $modx->runProcessor('workspace/packages/scanlocal', [
    'path' => dirname($package),
]);

if ($scan->isError()) {
    throw new RuntimeException($scan->getMessage());
}

$install = $modx->runProcessor('workspace/packages/install', [
    'package' => basename($package),
    'signature' => pathinfo($package, PATHINFO_FILENAME),
]);

if ($install->isError()) {
    throw new RuntimeException($install->getMessage());
}

echo "Transport Package installation: PASS" . PHP_EOL;
