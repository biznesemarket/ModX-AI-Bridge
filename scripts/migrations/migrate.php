<?php
declare(strict_types=1);

$root = rtrim((string)($_SERVER['MODX_ROOT'] ?? getenv('MODX_ROOT') ?: '/var/www/html'), '/') . '/';
if (!is_file($root . 'config.core.php')) {
    fwrite(STDERR, "MODX_ROOT is not a MODX installation: {$root}\n");
    exit(2);
}
require_once $root . 'config.core.php';
require_once MODX_CORE_PATH . 'vendor/autoload.php';
$modx = new MODX\Revolution\modX();
$modx->initialize('mgr');

$migrationsDir = dirname(__DIR__, 2) . '/core/components/aibridge/migrations';
$files = glob($migrationsDir . '/*.sql') ?: [];
sort($files, SORT_NATURAL);

$modx->exec("CREATE TABLE IF NOT EXISTS {$modx->getTableName('aibridge_migration')} (id INT UNSIGNED NOT NULL AUTO_INCREMENT, filename VARCHAR(255) NOT NULL, applied_at DATETIME NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_filename(filename)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$command = $argv[1] ?? 'status';
if ($command === 'status') {
    $rows = $modx->query("SELECT filename, applied_at FROM {$modx->getTableName('aibridge_migration')} ORDER BY id");
    foreach ($rows ?: [] as $row) fwrite(STDOUT, "applied {$row['filename']} {$row['applied_at']}\n");
    foreach ($files as $file) {
        $name = basename($file);
        $stmt = $modx->prepare("SELECT 1 FROM {$modx->getTableName('aibridge_migration')} WHERE filename = ? LIMIT 1");
        $stmt->execute([$name]);
        if (!$stmt->fetchColumn()) fwrite(STDOUT, "pending {$name}\n");
    }
    exit(0);
}

if ($command !== 'migrate') {
    fwrite(STDERR, "Usage: php migrate.php status|migrate\n");
    exit(2);
}

foreach ($files as $file) {
    $name = basename($file);
    $stmt = $modx->prepare("SELECT 1 FROM {$modx->getTableName('aibridge_migration')} WHERE filename = ? LIMIT 1");
    $stmt->execute([$name]);
    if ($stmt->fetchColumn()) continue;
    $sql = trim((string)file_get_contents($file));
    if ($sql === '') continue;
    $modx->beginTransaction();
    try {
        foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\R|$)/', $sql) ?: [])) as $statement) {
            $modx->exec($statement);
        }
        $insert = $modx->prepare("INSERT INTO {$modx->getTableName('aibridge_migration')} (filename, applied_at) VALUES (?, NOW())");
        $insert->execute([$name]);
        $modx->commit();
        fwrite(STDOUT, "applied {$name}\n");
    } catch (Throwable $e) {
        $modx->rollBack();
        fwrite(STDERR, "migration failed {$name}: {$e->getMessage()}\n");
        exit(1);
    }
}
