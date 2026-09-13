<?php

declare(strict_types=1);

/*
 * Current MODX 3 build configuration.
 *
 * MODX_ROOT must point to a real MODX 3.2+ installation.
 * The current ModExtra3 convention is _build/build.php + _build/config.inc.php.
 */

$modxRoot = getenv('MODX_ROOT');

if (!$modxRoot) {
    $modxRoot = dirname(__DIR__);
}

$modxRoot = rtrim($modxRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

return [
    'name' => 'ModX AI Bridge',
    'name_lower' => 'aibridge',
    'version' => '0.9.0',
    'release' => '',
    'install' => false,
    'modx_root' => $modxRoot,
];
