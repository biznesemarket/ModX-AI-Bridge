<?php

declare(strict_types=1);

/**
 * Applies the deterministic runtime configuration used by the integration
 * gates (REST/MCP enabled, HTTPS requirement disabled, loopback allowlist)
 * and refreshes the system settings cache so web requests observe it.
 *
 * Test/dev environments only; never run this against production.
 */

$root = rtrim((string) (getenv('MODX_ROOT') ?: '/var/www/html'), '/') . '/';
if (!is_file($root . 'config.core.php')) {
    fwrite(STDERR, "MODX_ROOT is not a MODX installation: {$root}\n");
    exit(2);
}

require_once $root . 'config.core.php';
require_once MODX_CORE_PATH . 'vendor/autoload.php';

$modx = new \MODX\Revolution\modX();
$modx->initialize('mgr');

$settings = [
    'aibridge_rest_enabled' => '1',
    'aibridge_mcp_enabled' => '1',
    'aibridge_require_https' => '0',
    'aibridge_ip_allowlist' => '["127.0.0.1"]',
    'aibridge_rate_limit_per_minute' => '1000',
    'aibridge_audit_enabled' => '1',
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
    if (!$setting->save()) {
        fwrite(STDERR, "Failed to persist setting {$key}\n");
        exit(1);
    }
}

$modx->getCacheManager()->refresh(['system_settings' => []]);

echo "Test runtime configuration: PASS" . PHP_EOL;
