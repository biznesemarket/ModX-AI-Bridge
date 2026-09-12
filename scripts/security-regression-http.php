<?php

declare(strict_types=1);

/**
 * HTTP-level security regression for the AIBridge REST boundary.
 *
 * Covers cases that are enforced by the web front controller rather than the
 * in-process RestApi: malformed JSON bodies, oversized bodies and transport
 * authentication. Run inside the MODX container after the test runtime is
 * configured (REST on, HTTPS off, loopback allowlist):
 *
 *   MODX_ROOT=/var/www/html php scripts/security-regression-http.php
 */

$root = rtrim((string) (getenv('MODX_ROOT') ?: '/var/www/html'), '/') . '/';
if (!is_file($root . 'config.core.php')) {
    fwrite(STDERR, "MODX_ROOT is not a MODX installation: {$root}\n");
    exit(2);
}

require_once $root . 'config.core.php';
require_once MODX_CORE_PATH . 'vendor/autoload.php';

$modx = new \MODX\Revolution\modX();
if (!$modx->initialize('mgr')) {
    fwrite(STDERR, "MODX initialization failed.\n");
    exit(3);
}

$corePath = MODX_CORE_PATH . 'components/aibridge/';
$modx->getLoader()->addPsr4('AIBridge\\', $corePath . 'src/');
$modx->addPackage('AIBridge\\Model', $corePath . 'src/', null, 'AIBridge\\');

$settings = [
    'aibridge_rest_enabled' => '1',
    'aibridge_require_https' => '0',
    'aibridge_ip_allowlist' => '["127.0.0.1"]',
    'aibridge_rate_limit_per_minute' => '1000',
];
foreach ($settings as $key => $value) {
    $setting = $modx->getObject(\MODX\Revolution\modSystemSetting::class, ['key' => $key]);
    if (!$setting) {
        $setting = $modx->newObject(\MODX\Revolution\modSystemSetting::class);
        $setting->set('key', $key);
        $setting->set('namespace', 'aibridge');
        $setting->set('area', 'tests');
    }
    $setting->set('value', $value);
    $setting->save();
}
$modx->getCacheManager()->refresh(['system_settings' => []]);

$suffix = bin2hex(random_bytes(4));
$profile = (new \AIBridge\MultiSite\ProfileService($modx))->create([
    'name' => 'SecurityHttp ' . $suffix,
    'site_key' => 'security-http-' . $suffix,
]);
$token = (new \AIBridge\Security\TokenManager($modx, \AIBridge\Configuration\ConfigFactory::fromModx($modx)))
    ->issue('security-http-' . $suffix, ['site:read', 'content:validate'], $profile->profileId);

function httpRequest(string $method, string $path, ?string $rawBody, array $headers): array
{
    $ch = curl_init('http://localhost/api/ai/v2' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($rawBody !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
    }
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => (string) $body];
}

$auth = ['Authorization: Bearer ' . $token['token'], 'Content-Type: application/json'];
$failures = [];

$checks = [
    'public_health' => static fn () => httpRequest('GET', '/health', null, [])['status'] === 200,
    'unknown_token_401' => static function () use ($auth) {
        $r = httpRequest('GET', '/capabilities', null, ['Authorization: Bearer invalid-token']);
        return $r['status'] === 401;
    },
    'malformed_json_400' => static function () use ($auth) {
        $r = httpRequest('POST', '/content/validate', '{"content": ', $auth);
        $decoded = json_decode($r['body'], true);
        return $r['status'] === 400 && ($decoded['error']['code'] ?? null) === 'invalid_json';
    },
    'oversized_body_413' => static function () use ($auth) {
        $raw = '{"content":{"pagetitle":"' . str_repeat('a', 1200000) . '"}}';
        $r = httpRequest('POST', '/content/validate', $raw, $auth);
        $decoded = json_decode($r['body'], true);
        return $r['status'] === 413 && ($decoded['error']['code'] ?? null) === 'request_too_large';
    },
    'valid_json_200' => static function () use ($auth) {
        $r = httpRequest('POST', '/content/validate', '{"content":{"pagetitle":"ok"},"contract":{"fields":[]}}', $auth);
        return $r['status'] === 200;
    },
];

foreach ($checks as $name => $check) {
    $ok = (bool) $check();
    echo ($ok ? 'PASS' : 'FAIL') . ' ' . $name . PHP_EOL;
    if (!$ok) {
        $failures[] = $name;
    }
}

if ($failures !== []) {
    fwrite(STDERR, 'HTTP security regression FAILED: ' . implode(', ', $failures) . PHP_EOL);
    exit(1);
}

echo 'HTTP SECURITY REGRESSION: PASS' . PHP_EOL;
