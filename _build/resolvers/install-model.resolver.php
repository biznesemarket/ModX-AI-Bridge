<?php

declare(strict_types=1);

/**
 * Install xPDO 3 model tables after package files have been copied.
 */

use AIBridge\Model\AuditEvent;
use AIBridge\Model\Fingerprint;
use AIBridge\Model\IdempotencyKey;
use AIBridge\Model\Job;
use AIBridge\Model\Policy;
use AIBridge\Model\RateLimitBucket;
use AIBridge\Model\Schema;
use AIBridge\Model\Snapshot;
use AIBridge\Model\Token;

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
    Token::class,
    AuditEvent::class,
    Job::class,
    Schema::class,
    Fingerprint::class,
    Policy::class,
    Snapshot::class,
    IdempotencyKey::class,
    RateLimitBucket::class,
];

foreach ($classes as $class) {
    if (!$manager->createObjectContainer($class)) {
        throw new RuntimeException(
            '[AIBridge] Failed to create table for ' . $class
        );
    }
}

return true;
