<?php

declare(strict_types=1);

use MODX\Revolution\modSystemSetting;

return [
    (new modSystemSetting($modx))->fromArray([
        'key' => 'aibridge_core_path',
        'value' => '{core_path}components/aibridge/',
        'xtype' => 'textfield',
        'namespace' => 'aibridge',
        'area' => 'paths',
    ]),
    (new modSystemSetting($modx))->fromArray([
        'key' => 'aibridge_assets_url',
        'value' => '{assets_url}components/aibridge/',
        'xtype' => 'textfield',
        'namespace' => 'aibridge',
        'area' => 'paths',
    ]),
    (new modSystemSetting($modx))->fromArray([
        'key' => 'aibridge_rest_enabled',
        'value' => '0',
        'xtype' => 'combo-boolean',
        'namespace' => 'aibridge',
        'area' => 'api',
    ]),
    (new modSystemSetting($modx))->fromArray([
        'key' => 'aibridge_mcp_enabled',
        'value' => '0',
        'xtype' => 'combo-boolean',
        'namespace' => 'aibridge',
        'area' => 'api',
    ]),
    (new modSystemSetting($modx))->fromArray([
        'key' => 'aibridge_require_https', 'value' => '1', 'xtype' => 'combo-boolean', 'namespace' => 'aibridge', 'area' => 'security',
    ]),
    (new modSystemSetting($modx))->fromArray([
        'key' => 'aibridge_token_expiry_days', 'value' => '90', 'xtype' => 'number', 'namespace' => 'aibridge', 'area' => 'security',
    ]),
    (new modSystemSetting($modx))->fromArray([
        'key' => 'aibridge_rate_limit_per_minute', 'value' => '60', 'xtype' => 'number', 'namespace' => 'aibridge', 'area' => 'security',
    ]),
    (new modSystemSetting($modx))->fromArray([
        'key' => 'aibridge_allow_resource_delete', 'value' => '0', 'xtype' => 'combo-boolean', 'namespace' => 'aibridge', 'area' => 'security',
    ]),
    (new modSystemSetting($modx))->fromArray([
        'key' => 'aibridge_allow_setting_write', 'value' => '0', 'xtype' => 'combo-boolean', 'namespace' => 'aibridge', 'area' => 'security',
    ]),
    (new modSystemSetting($modx))->fromArray([
        'key' => 'aibridge_approval_required_for_publish', 'value' => '1', 'xtype' => 'combo-boolean', 'namespace' => 'aibridge', 'area' => 'security',
    ]),
    (new modSystemSetting($modx))->fromArray([
        'key' => 'aibridge_ip_allowlist', 'value' => '[]', 'xtype' => 'textarea', 'namespace' => 'aibridge', 'area' => 'security',
    ]),
];
