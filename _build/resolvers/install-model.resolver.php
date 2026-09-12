<?php

declare(strict_types=1);

/**
 * Install xPDO 3 model tables after package files have been copied.
 *
 * xPDO executes PHP resolvers via include() inside xPDOVehicle::resolve(),
 * so the available context is $transport (and $this), not $modx.
 */

use AIBridge\Model\Approval;
use AIBridge\Model\AuditEvent;
use AIBridge\Model\ChangeRequest;
use AIBridge\Model\Fingerprint;
use AIBridge\Model\IdempotencyKey;
use AIBridge\Model\Job;
use AIBridge\Model\Policy;
use AIBridge\Model\Profile;
use AIBridge\Model\RateLimitBucket;
use AIBridge\Model\Schema;
use AIBridge\Model\Snapshot;
use AIBridge\Model\Token;

/** @var xPDO\Transport\xPDOTransport $transport */
if (!isset($transport) || !is_object($transport) || !isset($transport->xpdo)) {
    fwrite(STDERR, "[AIBridge] transport context is missing for install-model resolver\n");
    return false;
}

$modx = $transport->xpdo;

$corePath = MODX_CORE_PATH . 'components/aibridge/';

$modx->getLoader()->addPsr4('AIBridge\\', $corePath . 'src/');
$modx->addPackage(
    'AIBridge\\Model',
    $corePath . 'src/',
    null,
    'AIBridge\\'
);

$manager = $modx->getManager();

$classes = [
    Profile::class,
    Token::class,
    AuditEvent::class,
    Job::class,
    Schema::class,
    Fingerprint::class,
    Policy::class,
    Snapshot::class,
    IdempotencyKey::class,
    RateLimitBucket::class,
    ChangeRequest::class,
    Approval::class,
];

foreach ($classes as $class) {
    if (!$manager->createObjectContainer($class)) {
        throw new RuntimeException(
            '[AIBridge] Failed to create table for ' . $class
        );
    }
}

return true;
