<?php

declare(strict_types=1);

namespace AIBridge\Configuration;

use MODX\Revolution\modX;

final class ConfigFactory
{
    public static function fromModx(modX $modx): BridgeConfig
    {
        $defaults = require dirname(__DIR__, 2) . '/config/config.php';
        $settings = [];

        foreach ($defaults as $key => $default) {
            $settingKey = 'aibridge.' . $key;
            $settings[$key] = $modx->getOption($settingKey, [], $default);
        }

        return new BridgeConfig($settings);
    }
}
