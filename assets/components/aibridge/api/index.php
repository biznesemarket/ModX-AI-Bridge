<?php

declare(strict_types=1);

/**
 * AIBridge REST/MCP front controller.
 *
 * Reachable as /api/ai/v2/* via the documented web-server mapping, or directly
 * as /assets/components/aibridge/api/index.php/<path> for diagnostics.
 */

$candidates = array_filter([
    getenv('MODX_ROOT') ?: null,
    $_SERVER['DOCUMENT_ROOT'] ?? null,
    dirname(__DIR__, 4),
    dirname(__DIR__, 5),
]);

$root = null;
foreach ($candidates as $candidate) {
    $candidate = rtrim((string) $candidate, '/\\');
    if ($candidate !== '' && is_file($candidate . '/config.core.php')) {
        $root = $candidate;
        break;
    }
}

if ($root === null) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => ['code' => 'bootstrap_failed', 'message' => 'MODX bootstrap file was not found.']]);
    exit;
}

require_once $root . '/config.core.php';
require_once MODX_CORE_PATH . 'vendor/autoload.php';

$modx = new \MODX\Revolution\modX();
if (!$modx->initialize('web')) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => ['code' => 'bootstrap_failed', 'message' => 'MODX could not be initialized.']]);
    exit;
}

$corePath = MODX_CORE_PATH . 'components/aibridge/';
$modx->getLoader()->addPsr4('AIBridge\\', $corePath . 'src/');
$modx->addPackage('AIBridge\\Model', $corePath . 'src/', null, 'AIBridge\\');

$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

$path = (string) ($_SERVER['PATH_INFO'] ?? '');
if ($path === '') {
    $uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    $prefix = '/api/ai/v2';
    $position = strpos($uri, $prefix);
    $path = $position !== false ? substr($uri, $position + strlen($prefix)) : $uri;
}

$headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $serverKey) {
    if (!isset($headers['Authorization']) && isset($_SERVER[$serverKey])) {
        $headers['Authorization'] = (string) $_SERVER[$serverKey];
    }
}
if (!isset($headers['Idempotency-Key']) && isset($_SERVER['HTTP_IDEMPOTENCY_KEY'])) {
    $headers['Idempotency-Key'] = (string) $_SERVER['HTTP_IDEMPOTENCY_KEY'];
}

$raw = (string) (file_get_contents('php://input') ?: '');
$maxBytes = (int) $modx->getOption('aibridge_max_request_body_bytes', null, 1048576);
if (strlen($raw) > $maxBytes) {
    http_response_code(413);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => ['code' => 'request_too_large', 'message' => 'Request body exceeds the configured limit.']]);
    exit;
}

$body = [];
if ($raw !== '') {
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => ['code' => 'invalid_json', 'message' => 'Request body must be a valid JSON object.']]);
        exit;
    }
    $body = $decoded;
}

$requireHttps = (bool) $modx->getOption('aibridge_require_https', null, true);
$isHttps = !empty($_SERVER['HTTPS'])
    || (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
if ($requireHttps && !$isHttps && $path !== '/health') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => ['code' => 'https_required', 'message' => 'HTTPS is required by the Bridge configuration.']]);
    exit;
}

$clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$query = is_array($_GET) ? $_GET : [];

$result = (new \AIBridge\Api\RestApi($modx))->handle($method, $path, $headers, $body, $clientIp, $query);

http_response_code((int) $result['status']);
foreach ((array) $result['headers'] as $name => $value) {
    header($name . ': ' . $value);
}
echo json_encode($result['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
