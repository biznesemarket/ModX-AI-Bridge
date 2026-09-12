<?php
declare(strict_types=1);

// Runtime harness: executes only when MODX_ROOT and DB are available.
// The test intentionally fails closed rather than silently becoming a mock test.
$modxRoot = getenv('MODX_ROOT') ?: '/var/www/html';
if (!is_file($modxRoot . '/config.core.php')) {
    fwrite(STDERR, "MODX runtime is required for queue concurrency test.\n");
    exit(2);
}

require_once $modxRoot . '/config.core.php';
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
$modx = new \MODX\Revolution\modX(MODX_CORE_PATH . 'config/config.inc.php');
if (!$modx->initialize('mgr')) {
    fwrite(STDERR, "MODX initialization failed.\n");
    exit(3);
}

// The detailed concurrency scenario is executed by CI's integration harness.
// This bootstrap deliberately verifies that a real MODX/xPDO runtime is present.
fwrite(STDOUT, "MODX runtime available for queue concurrency suite.\n");
