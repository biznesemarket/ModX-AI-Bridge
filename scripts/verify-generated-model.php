<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$models = [
    'Token',
    'AuditEvent',
    'Job',
    'Schema',
    'Fingerprint',
    'Policy',
    'Snapshot',
];

$failed = [];

foreach ($models as $model) {
    $path = $root . '/core/components/aibridge/src/Model/' . $model . '.php';
    if (!is_file($path)) {
        $failed[] = $model . '.php';
    }
}

if (!is_file($root . '/core/components/aibridge/src/Model/metadata.mysql.php')) {
    $failed[] = 'metadata.mysql.php';
}

if ($failed) {
    fwrite(STDERR, "Generated model verification FAILED:" . PHP_EOL);
    foreach ($failed as $item) {
        fwrite(STDERR, " - {$item}" . PHP_EOL);
    }
    exit(1);
}

echo "Generated model verification: PASS" . PHP_EOL;
