<?php

declare(strict_types=1);

/**
 * Provisions the runtime context for the TypeScript SDK live HTTP E2E.
 *
 * Test/dev environments only; never run this against production. Prints one
 * JSON object on stdout (the token is intentionally included because the host
 * runner needs it; never echo the output in CI logs).
 *
 * Usage (inside the MODX container):
 *   MODX_ROOT=/var/www/html php scripts/ts-live-runtime.php
 *   MODX_ROOT=/var/www/html php scripts/ts-live-runtime.php --restore-allowlist
 *
 * Env:
 *   AIBRIDGE_TEST_IP_ALLOWLIST  JSON array for aibridge_ip_allowlist (default ["127.0.0.1"])
 *   AIBRIDGE_LIVE_BASE_URL      base URL echoed to the context (default http://localhost:8080)
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
if (!is_dir($corePath)) {
    fwrite(STDERR, "AIBridge component is not installed: {$corePath}\n");
    exit(4);
}
$modx->getLoader()->addPsr4('AIBridge\\', $corePath . 'src/');
$modx->addPackage('AIBridge\\Model', $corePath . 'src/', null, 'AIBridge\\');

$restore = in_array('--restore-allowlist', $argv ?? [], true);
$allowlist = '["127.0.0.1"]';
if (!$restore) {
    $fromEnv = (string) (getenv('AIBRIDGE_TEST_IP_ALLOWLIST') ?: '');
    if ($fromEnv !== '') {
        $decoded = json_decode($fromEnv, true);
        if (!is_array($decoded) || $decoded === []) {
            fwrite(STDERR, "AIBRIDGE_TEST_IP_ALLOWLIST must be a non-empty JSON array.\n");
            exit(5);
        }
        $allowlist = json_encode(array_values(array_map('strval', $decoded)), JSON_UNESCAPED_SLASHES);
    }
}

$settings = [
    'aibridge_rest_enabled' => '1',
    'aibridge_mcp_enabled' => '1',
    'aibridge_require_https' => '0',
    'aibridge_ip_allowlist' => $allowlist,
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

if ($restore) {
    echo 'TS LIVE RUNTIME: RESTORED' . PHP_EOL;
    exit(0);
}

$config = \AIBridge\Configuration\ConfigFactory::fromModx($modx);
$profiles = new \AIBridge\MultiSite\ProfileService($modx);
$siteKey = 'ts-live-e2e';
$profile = $profiles->getBySiteKey($siteKey);
if (!$profile) {
    $profile = $profiles->create(['name' => 'TS Live E2E', 'site_key' => $siteKey, 'environment' => 'test']);
}
$profileId = $profile->profileId;

$tokens = new \AIBridge\Security\TokenManager($modx, $config);
foreach ($modx->getCollection(\AIBridge\Model\Token::class, ['profile_id' => $profileId, 'name' => 'ts-live-e2e', 'status' => 'active']) ?: [] as $old) {
    $tokens->revoke((int) $old->get('id'));
}
$issued = $tokens->issue('ts-live-e2e', [
    'site:read', 'content:validate', 'resource:write', 'resource:preview', 'resource:read', 'resource:delete', 'resource:publish',
], $profileId);

$template = null;
foreach ($modx->getCollection(\MODX\Revolution\modTemplate::class, [], ['limit' => 1, 'sortby' => 'id', 'sortdir' => 'ASC']) ?: [] as $row) {
    $template = $row;
}
if (!$template) {
    $template = $modx->newObject(\MODX\Revolution\modTemplate::class);
    $template->set('templatename', 'AIBridge TS Live E2E');
    $template->set('content', '');
    if (!$template->save()) {
        fwrite(STDERR, "Failed to create a template for the live test.\n");
        exit(6);
    }
}

echo json_encode([
    'base_url' => (string) (getenv('AIBRIDGE_LIVE_BASE_URL') ?: 'http://localhost:8080'),
    'token' => (string) $issued['token'],
    'token_id' => (string) $issued['id'],
    'profile_id' => $profileId,
    'template_id' => (int) $template->get('id'),
    'site_key' => $siteKey,
], JSON_UNESCAPED_SLASHES) . PHP_EOL;
