<?php

declare(strict_types=1);

$requiredExtensions = [
    'curl', 'dom', 'fileinfo', 'gd', 'json', 'pdo', 'pdo_mysql',
    'simplexml', 'xml', 'xmlwriter', 'zip', 'zlib'
];

$missing = [];

foreach ($requiredExtensions as $extension) {
    if (!extension_loaded($extension)) {
        $missing[] = $extension;
    }
}

if ($missing) {
    fwrite(STDERR, "Missing PHP extensions: " . implode(', ', $missing) . PHP_EOL);
    exit(2);
}

if (PHP_VERSION_ID < 80100) {
    fwrite(STDERR, "PHP 8.1+ is required." . PHP_EOL);
    exit(2);
}

echo "PHP runtime preflight: PASS" . PHP_EOL;
echo "PHP: " . PHP_VERSION . PHP_EOL;
echo "Extensions: PASS" . PHP_EOL;
