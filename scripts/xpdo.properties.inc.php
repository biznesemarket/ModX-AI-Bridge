<?php

declare(strict_types=1);

/**
 * xPDO CLI generator configuration.
 *
 * Development/test credentials only; never use production secrets here.
 */

$host = getenv('AIBRIDGE_DB_HOST') ?: 'db';
$name = getenv('AIBRIDGE_DB_NAME') ?: 'modx';
$user = getenv('AIBRIDGE_DB_USER') ?: 'modx';
$password = getenv('AIBRIDGE_DB_PASSWORD') ?: 'modx';

return [
    'mysql_array_options' => [
        \xPDO\xPDO::OPT_HYDRATE_FIELDS => true,
        \xPDO\xPDO::OPT_HYDRATE_RELATED_OBJECTS => true,
        \xPDO\xPDO::OPT_HYDRATE_ADHOC_FIELDS => true,
        \xPDO\xPDO::OPT_CONNECTIONS => [
            [
                'dsn' => "mysql:host={$host};dbname={$name};charset=utf8mb4",
                'username' => $user,
                'password' => $password,
                'options' => [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
                'driverOptions' => [\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'"],
            ],
        ],
    ],
];
