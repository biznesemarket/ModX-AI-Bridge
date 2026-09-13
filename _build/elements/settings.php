<?php

declare(strict_types=1);

use MODX\Revolution\modSystemSetting;

/*
 * xPDOObject::fromArray() returns void, so it cannot be chained off `new`.
 * Build each setting in two steps so the array contains modSystemSetting objects
 * (build.php skips anything that is not a modSystemSetting).
 */
$setting = static function (array $fields) use ($modx): modSystemSetting {
    $object = new modSystemSetting($modx);
    $object->fromArray($fields, '', true);
    return $object;
};

return [
    $setting([
        'key' => 'aibridge_core_path',
        'value' => '{core_path}components/aibridge/',
        'xtype' => 'textfield',
        'namespace' => 'aibridge',
        'area' => 'paths',
    ]),
    $setting([
        'key' => 'aibridge_assets_url',
        'value' => '{assets_url}components/aibridge/',
        'xtype' => 'textfield',
        'namespace' => 'aibridge',
        'area' => 'paths',
    ]),
    $setting([
        'key' => 'aibridge_rest_enabled',
        'value' => '0',
        'xtype' => 'combo-boolean',
        'namespace' => 'aibridge',
        'area' => 'api',
    ]),
    $setting([
        'key' => 'aibridge_mcp_enabled',
        'value' => '0',
        'xtype' => 'combo-boolean',
        'namespace' => 'aibridge',
        'area' => 'api',
    ]),
    $setting([
        'key' => 'aibridge_require_https', 'value' => '1', 'xtype' => 'combo-boolean', 'namespace' => 'aibridge', 'area' => 'security',
    ]),
    $setting([
        'key' => 'aibridge_token_expiry_days', 'value' => '90', 'xtype' => 'number', 'namespace' => 'aibridge', 'area' => 'security',
    ]),
    $setting([
        'key' => 'aibridge_rate_limit_per_minute', 'value' => '60', 'xtype' => 'number', 'namespace' => 'aibridge', 'area' => 'security',
    ]),
    $setting([
        'key' => 'aibridge_allow_resource_delete', 'value' => '0', 'xtype' => 'combo-boolean', 'namespace' => 'aibridge', 'area' => 'security',
    ]),
    $setting([
        'key' => 'aibridge_allow_setting_write', 'value' => '0', 'xtype' => 'combo-boolean', 'namespace' => 'aibridge', 'area' => 'security',
    ]),
    $setting([
        'key' => 'aibridge_approval_required_for_publish', 'value' => '1', 'xtype' => 'combo-boolean', 'namespace' => 'aibridge', 'area' => 'security',
    ]),
    $setting([
        'key' => 'aibridge_ip_allowlist', 'value' => '[]', 'xtype' => 'textarea', 'namespace' => 'aibridge', 'area' => 'security',
    ]),
    $setting([
        'key' => 'aibridge_blocked_operations', 'value' => '["settings.write"]', 'xtype' => 'textarea', 'namespace' => 'aibridge', 'area' => 'security',
    ]),
    $setting([
        'key' => 'aibridge_security_fail_closed', 'value' => '1', 'xtype' => 'combo-boolean', 'namespace' => 'aibridge', 'area' => 'security',
    ]),
    $setting([
        'key' => 'aibridge_max_request_body_bytes', 'value' => '1048576', 'xtype' => 'number', 'namespace' => 'aibridge', 'area' => 'api',
    ]),
    $setting([
        'key' => 'aibridge_idempotency_ttl_seconds', 'value' => '86400', 'xtype' => 'number', 'namespace' => 'aibridge', 'area' => 'api',
    ]),
    $setting([
        'key' => 'aibridge_audit_enabled', 'value' => '1', 'xtype' => 'combo-boolean', 'namespace' => 'aibridge', 'area' => 'operations',
    ]),
    $setting([
        'key' => 'aibridge_queue_enabled', 'value' => '0', 'xtype' => 'combo-boolean', 'namespace' => 'aibridge', 'area' => 'operations',
    ]),
    $setting([
        'key' => 'aibridge_environment', 'value' => 'production', 'xtype' => 'textfield', 'namespace' => 'aibridge', 'area' => 'operations',
    ]),
    $setting([
        'key' => 'aibridge_version', 'value' => '0.6.0', 'xtype' => 'textfield', 'namespace' => 'aibridge', 'area' => 'operations',
    ]),
    $setting([
        'key' => 'aibridge_api_version', 'value' => 'v2', 'xtype' => 'textfield', 'namespace' => 'aibridge', 'area' => 'api',
    ]),
];
