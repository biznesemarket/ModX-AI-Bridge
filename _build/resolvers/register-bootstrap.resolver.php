<?php

/** @var \MODX\Revolution\modX $modx */

$namespacePath = MODX_CORE_PATH . 'components/aibridge/';
$bootstrap = $namespacePath . 'bootstrap.php';

if (!is_file($bootstrap)) {
    $modx->log(MODX_LOG_LEVEL_ERROR, '[AIBridge] bootstrap.php not found after file installation.');
    return false;
}

return true;
