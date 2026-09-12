<?php

declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/install-package.php /absolute/path/package.transport.zip\n");
    exit(2);
}

$packageFile = $argv[1];
$root = rtrim((string) (getenv('MODX_ROOT') ?: '/var/www/html'), '/') . '/';

if (!is_file($root . 'config.core.php')) {
    throw new RuntimeException('MODX_ROOT is not a MODX installation: ' . $root);
}

require $root . 'config.core.php';
require_once MODX_CORE_PATH . 'vendor/autoload.php';

$modx = \MODX\Revolution\modX::getInstance();
$modx->initialize('mgr');

$package = realpath($packageFile);
if ($package === false || !is_file($package)) {
    throw new RuntimeException('Transport package not found: ' . $packageFile);
}

$signature = (string) preg_replace('/\.transport\.zip$/', '', basename($package));

$scan = $modx->runProcessor(\MODX\Revolution\Processors\Workspace\Packages\ScanLocal::class, [
    'workspace' => 1,
]);

if ($scan->isError()) {
    throw new RuntimeException('Package scan failed: ' . $scan->getMessage() . logTail());
}

$install = $modx->runProcessor(\MODX\Revolution\Processors\Workspace\Packages\Install::class, [
    'signature' => $signature,
]);

if ($install->isError()) {
    $response = $install->getResponse();
    $details = is_array($response) ? json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    throw new RuntimeException('Package install failed: ' . $install->getMessage() . ' ' . $details . logTail());
}

echo "Transport Package installation: PASS" . PHP_EOL;
echo "Signature: {$signature}" . PHP_EOL;

function logTail(int $lines = 15): string
{
    $log = MODX_CORE_PATH . 'cache/logs/error.log';
    if (!is_file($log)) {
        return '';
    }
    $entries = @file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    return $entries === [] ? '' : "\nMODX log tail:\n" . implode("\n", array_slice($entries, -$lines));
}
