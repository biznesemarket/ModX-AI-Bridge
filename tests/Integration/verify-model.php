<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2) . '/core/components/aibridge/src/Model/';
$metadata = $root . 'metadata.mysql.php';
$mysql = $root . 'mysql/';

$required = [
    $metadata,
    $root . 'Token.php',
    $root . 'AuditEvent.php',
    $root . 'Job.php',
    $root . 'Schema.php',
    $root . 'Fingerprint.php',
    $root . 'Policy.php',
    $root . 'Snapshot.php',
];

foreach ($required as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Missing generated model artifact: {$file}\n");
        exit(1);
    }
}

foreach (['Token.php', 'AuditEvent.php', 'Job.php', 'Schema.php', 'Fingerprint.php', 'Policy.php', 'Snapshot.php'] as $map) {
    if (!is_file($mysql . $map)) {
        fwrite(STDERR, "Missing generated xPDO platform map: {$mysql}{$map}\n");
        exit(1);
    }
}

$data = require $metadata;
if (($data['version'] ?? null) !== '3.0') {
    fwrite(STDERR, "metadata.mysql.php is not xPDO 3 metadata.\n");
    exit(1);
}

if (($data['namespace'] ?? null) !== 'AIBridge\\Model') {
    fwrite(STDERR, "Unexpected model namespace.\n");
    exit(1);
}

if (($data['namespacePrefix'] ?? null) !== 'AIBridge\\') {
    fwrite(STDERR, "Unexpected namespace prefix.\n");
    exit(1);
}

fwrite(STDOUT, "Generated xPDO model artifacts verified.\n");
