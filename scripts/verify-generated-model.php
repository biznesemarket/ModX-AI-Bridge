<?php

declare(strict_types=1);

/**
 * Fail-closed verification of the generated xPDO model.
 *
 * Verifies that every schema class has a base class and a platform subclass and
 * that no file still contains unresolved generator template markers such as
 * "[+class-header+]".
 */

$root = dirname(__DIR__);
$modelDir = $root . '/core/components/aibridge/src/Model';
$platformDir = $modelDir . '/mysql';

$classes = [
    'Profile',
    'Token',
    'AuditEvent',
    'Job',
    'Schema',
    'Fingerprint',
    'Policy',
    'Snapshot',
    'IdempotencyKey',
    'RateLimitBucket',
    'ChangeRequest',
    'Approval',
];

$failed = [];

$assertValid = static function (string $path, array $needles) use (&$failed): void {
    if (!is_file($path)) {
        $failed[] = basename($path) . ': missing';
        return;
    }
    $contents = (string) file_get_contents($path);
    if (str_contains($contents, '[+')) {
        $failed[] = basename($path) . ': contains unresolved template markers';
        return;
    }
    foreach ($needles as $needle) {
        if (!str_contains($contents, $needle)) {
            $failed[] = basename($path) . ": missing '{$needle}'";
            return;
        }
    }
};

foreach ($classes as $class) {
    $assertValid($modelDir . '/' . $class . '.php', ['<?php', 'class ' . $class]);
    $assertValid($platformDir . '/' . $class . '.php', ['<?php', 'namespace AIBridge\\Model\\mysql;', 'class ' . $class]);
}

$assertValid($modelDir . '/metadata.mysql.php', ['<?php', "'namespace' => 'AIBridge\\\\Model'"]);

if ($failed) {
    fwrite(STDERR, "Generated model verification FAILED:" . PHP_EOL);
    foreach ($failed as $item) {
        fwrite(STDERR, " - {$item}" . PHP_EOL);
    }
    exit(1);
}

echo "Generated model verification: PASS" . PHP_EOL;
