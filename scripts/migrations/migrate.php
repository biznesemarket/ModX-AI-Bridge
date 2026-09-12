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

$connection = $modx->getConnection();
$pdo = is_object($connection) ? ($connection->pdo ?? null) : null;
if (!$pdo instanceof \PDO) {
    fwrite(STDERR, "MODX database connection unavailable.\n");
    exit(3);
}
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

$migrationsDir = dirname(__DIR__, 2) . '/core/components/aibridge/migrations';
$files = glob($migrationsDir . '/*.sql') ?: [];
sort($files, SORT_NATURAL);

$tablePrefix = (string) $modx->getOption('table_prefix', null, 'modx_');
$migrationTable = $tablePrefix . 'aibridge_migrations';

$pdo->exec("CREATE TABLE IF NOT EXISTS {$migrationTable} (id INT UNSIGNED NOT NULL AUTO_INCREMENT, filename VARCHAR(255) NOT NULL, applied_at DATETIME NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_filename(filename)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$command = $argv[1] ?? 'status';
if ($command === 'status') {
    $rows = $pdo->query("SELECT filename, applied_at FROM {$migrationTable} ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($rows as $row) fwrite(STDOUT, "applied {$row['filename']} {$row['applied_at']}\n");
    foreach ($files as $file) {
        $name = basename($file);
        $stmt = $pdo->prepare("SELECT 1 FROM {$migrationTable} WHERE filename = ? LIMIT 1");
        $stmt->execute([$name]);
        if (!$stmt->fetchColumn()) fwrite(STDOUT, "pending {$name}\n");
    }
    exit(0);
}

if ($command !== 'migrate') {
    fwrite(STDERR, "Usage: php migrate.php status|migrate\n");
    exit(2);
}

// DDL statements implicitly commit on MySQL, so migrations are applied
// statement-by-statement and recorded only after the whole file succeeded.
foreach ($files as $file) {
    $name = basename($file);
    $stmt = $pdo->prepare("SELECT 1 FROM {$migrationTable} WHERE filename = ? LIMIT 1");
    $stmt->execute([$name]);
    if ($stmt->fetchColumn()) {
        fwrite(STDOUT, "skipped {$name} (already applied)\n");
        continue;
    }
    $raw = trim((string)file_get_contents($file));
    if ($raw === '') continue;
    $sql = str_replace(['[[+prefix]]', '{PREFIX}'], $tablePrefix, $raw);
    try {
        foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\R|$)/', $sql) ?: [])) as $statement) {
            $pdo->exec($statement);
        }
        $insert = $pdo->prepare("INSERT INTO {$migrationTable} (filename, applied_at) VALUES (?, NOW())");
        $insert->execute([$name]);
        fwrite(STDOUT, "applied {$name}\n");
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        fwrite(STDERR, "migration failed {$name}: {$e->getMessage()}\n");
        exit(1);
    }
}
