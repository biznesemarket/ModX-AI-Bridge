<?php

declare(strict_types=1);

namespace AIBridge\Configuration;

use MODX\Revolution\modX;

final class ConfigFactory
{
    private const JSON_KEYS = ['ip_allowlist', 'blocked_operations'];

    public static function fromModx(modX $modx): BridgeConfig
    {
        $defaults = require dirname(__DIR__, 2) . '/config/config.php';
        $settings = [];

        foreach ($defaults as $key => $default) {
            $settingKey = 'aibridge_' . $key;
            $value = $modx->getOption($settingKey, [], $default);
            if (in_array($key, self::JSON_KEYS, true) && is_string($value)) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $value = $decoded;
                }
            }
            $settings[$key] = $value;
        }

        return new BridgeConfig($settings);
    }
}
