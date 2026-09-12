<?php

declare(strict_types=1);

$root = getenv('MODX_ROOT') ?: '/var/www/html';

require $root . '/config.core.php';
require_once MODX_CORE_PATH . 'vendor/autoload.php';

$modx = \MODX\Revolution\modX::getInstance();
if (!$modx instanceof \MODX\Revolution\modX) {
    throw new RuntimeException('MODX instance was not created.');
}

if (!$modx->initialize('mgr')) {
    throw new RuntimeException('MODX manager context failed to initialize.');
}

$namespace = $modx->getObject(\MODX\Revolution\modNamespace::class, ['name' => 'aibridge']);
if (!$namespace) {
    throw new RuntimeException('aibridge namespace was not installed.');
}

$corePath = MODX_CORE_PATH . 'components/aibridge/';
$modx->getLoader()->addPsr4('AIBridge\\', $corePath . 'src/');
$modx->addPackage('AIBridge\\Model', $corePath . 'src/', null, 'AIBridge\\');

$modelClasses = [
    \AIBridge\Model\Token::class,
    \AIBridge\Model\AuditEvent::class,
    \AIBridge\Model\Job::class,
    \AIBridge\Model\Schema::class,
    \AIBridge\Model\Fingerprint::class,
    \AIBridge\Model\Policy::class,
    \AIBridge\Model\Snapshot::class,
];

foreach ($modelClasses as $class) {
    $table = $modx->getTableName($class);
    if (!is_string($table) || $table === '') {
        throw new RuntimeException("No table mapping for {$class}");
    }
}

$service = $modx->services->get('aibridge.application');
if (!$service instanceof \AIBridge\Application\Application) {
    throw new RuntimeException('aibridge.application service is not registered.');
}

$health = $service->health();
if (($health['status'] ?? null) !== 'ok') {
    throw new RuntimeException('Application health check failed.');
}

$menu = $modx->getObject(\MODX\Revolution\modMenu::class, [
    'text' => 'aibridge',
]);
if (!$menu) {
    throw new RuntimeException('aibridge Manager menu was not installed.');
}

$processor = $modx->runProcessor(
    'health',
    [],
    [
        'processors_path' => $corePath . 'processors/',
    ]
);

if ($processor->isError()) {
    throw new RuntimeException('Health processor returned an error.');
}

$result = $processor->getResponse();
if (!is_array($result) && !is_object($result)) {
    throw new RuntimeException('Unexpected health processor response.');
}

echo "MODX runtime verification: PASS" . PHP_EOL;
echo "Namespace: PASS" . PHP_EOL;
echo "xPDO models: PASS" . PHP_EOL;
echo "Service container: PASS" . PHP_EOL;
echo "Manager menu: PASS" . PHP_EOL;
echo "Processor: PASS" . PHP_EOL;
