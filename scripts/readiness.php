<?php
declare(strict_types=1);
$root = rtrim((string)($_SERVER['MODX_ROOT'] ?? getenv('MODX_ROOT') ?: '/var/www/html'), '/') . '/';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!is_file($root . 'config.core.php')) {
    http_response_code(503);
    echo json_encode(['component'=>'modx-ai-bridge','status'=>'not_ready','checks'=>['modx_bootstrap'=>false]]);
    exit;
}
require_once $root . 'config.core.php';
require_once MODX_CORE_PATH . 'vendor/autoload.php';
try {
    $modx = new MODX\Revolution\modX();
    $modx->initialize('mgr');
    $checks = [
        'modx_bootstrap' => true,
        'database' => (bool)$modx->query('SELECT 1'),
        'service_container' => (bool)$modx->services,
        'aibridge_namespace' => (bool)$modx->getObject(\MODX\Revolution\modNamespace::class, ['name'=>'aibridge']),
    ];
    $ready = !in_array(false, $checks, true);
    http_response_code($ready ? 200 : 503);
    echo json_encode([
        'component' => 'modx-ai-bridge',
        'version' => (string)$modx->getOption('aibridge_version', null, '0.2.0'),
        'status' => $ready ? 'ready' : 'not_ready',
        'checks' => $checks,
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['component'=>'modx-ai-bridge','status'=>'not_ready','checks'=>['modx_bootstrap'=>false]]);
}
