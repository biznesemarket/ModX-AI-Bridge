<?php

/**
 * Verify that the component bootstrap was installed with the package.
 *
 * xPDO executes PHP resolvers via include() inside xPDOVehicle::resolve(),
 * so the available context is $transport (and $this), not $modx.
 */

/** @var xPDO\Transport\xPDOTransport $transport */
if (!isset($transport) || !is_object($transport) || !isset($transport->xpdo)) {
    fwrite(STDERR, "[AIBridge] transport context is missing for register-bootstrap resolver\n");
    return false;
}

$modx = $transport->xpdo;

$namespacePath = MODX_CORE_PATH . 'components/aibridge/';
$bootstrap = $namespacePath . 'bootstrap.php';

if (!is_file($bootstrap)) {
    $modx->log(MODX_LOG_LEVEL_ERROR, '[AIBridge] bootstrap.php not found after file installation.');
    return false;
}

return true;
