<?php

declare(strict_types=1);

return [
    'namespace' => 'aibridge',
    'version' => '0.1.0',
    'api_version' => 'v2',
    'environment' => 'development',
    'rest_enabled' => false,
    'mcp_enabled' => false,
    'audit_enabled' => true,
    'queue_enabled' => false,
    'require_https' => true,
    'token_expiry_days' => 90,
    'rate_limit_per_minute' => 60,
    'max_request_body_bytes' => 1048576,
    'approval_required_for_publish' => true,
    'allow_resource_delete' => false,
    'allow_setting_write' => false,
    'ip_allowlist' => [],
    'blocked_operations' => ['settings.write'],
    'idempotency_ttl_seconds' => 86400,
    'security_fail_closed' => true,
];
