<?php

declare(strict_types=1);

/**
 * @var \MODX\Revolution\modX $modx
 * @var array $namespace
 */

if (!isset($modx) || !$modx instanceof \MODX\Revolution\modX) {
    return;
}

$namespacePath = rtrim((string)($namespace['path'] ?? MODX_CORE_PATH . 'components/aibridge/'), '/') . '/';

// MODX 3 / xPDO 3 namespaced model package.
$modx->addPackage(
    'AIBridge\\Model',
    $namespacePath . 'src/',
    null,
    'AIBridge\\'
);

// Application PSR-4 classes. xPDO models are registered separately above.
$modx->getLoader()->addPsr4('AIBridge\\', $namespacePath . 'src/');

// MODX 3 custom services belong in the Pimple-based service container.
if ($modx->services && !$modx->services->has('aibridge.application')) {
    $modx->services->add('aibridge.application', static function () use ($modx) {
        return new \AIBridge\Application\Application($modx);
    });
}
