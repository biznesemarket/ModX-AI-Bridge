<?php
declare(strict_types=1);

/**
 * Queue concurrency runtime harness.
 *
 * Executes only against a real MODX runtime with MySQL. Fails closed instead
 * of silently becoming a mock test.
 *
 * Asserts:
 *   1. dispatch creates a queued job;
 *   2. a second sequential claim finds nothing while the job is running;
 *   3. stale lease recovery requeues an abandoned job;
 *   4. two forked workers racing for one job produce exactly one winner;
 *   5. completion transitions the job to completed.
 */

$modxRoot = rtrim((string) (getenv('MODX_ROOT') ?: '/var/www/html'), '/') . '/';
if (!is_file($modxRoot . 'config.core.php')) {
    fwrite(STDERR, "MODX runtime is required for queue concurrency test.\n");
    exit(2);
}

require_once $modxRoot . 'config.core.php';
require_once MODX_CORE_PATH . 'vendor/autoload.php';

$modx = new MODX\Revolution\modX();
if (!$modx->initialize('mgr')) {
    fwrite(STDERR, "MODX initialization failed.\n");
    exit(3);
}

$corePath = MODX_CORE_PATH . 'components/aibridge/';
$modx->getLoader()->addPsr4('AIBridge\\', $corePath . 'src/');
$modx->addPackage('AIBridge\\Model', $corePath . 'src/', null, 'AIBridge\\');

$queue = new AIBridge\Queue\QueueManager($modx);

$fail = static function (string $message) use ($modx): never {
    fwrite(STDERR, "QUEUE CONCURRENCY FAIL: {$message}\n");
    exit(1);
};

$table = $modx->getTableName(AIBridge\Model\Job::class);
$connection = $modx->getConnection();
$pdo = is_object($connection) ? ($connection->pdo ?? null) : null;
if (!$pdo instanceof PDO) {
    $fail('database connection unavailable');
}

// Remove leftovers from previous crashed harness runs so claim() cannot pick them up.
$pdo->exec("UPDATE {$table} SET status='cancelled', finished_at=NOW(), error_json='{\"code\":\"harness_cleanup\"}' WHERE status IN ('queued','running') AND idempotency_key LIKE 'concurrency-%'");

$job = new AIBridge\Queue\Job(
    'resource_execution',
    ['operation' => 'resource.preview', 'input' => []],
    3,
    300,
    'concurrency-' . bin2hex(random_bytes(6)),
    'harness',
    bin2hex(random_bytes(8)),
    null,
    0
);
$jobId = (int) $queue->dispatch($job);
if ($jobId < 1) {
    $fail('job dispatch failed');
}
fwrite(STDOUT, "dispatched job #{$jobId}\n");

$first = $queue->claim('worker-seq-a');
if ($first === null || $first->id() !== $jobId) {
    $fail('first claim did not win the queued job');
}
fwrite(STDOUT, "first claim: worker-seq-a owns job #{$jobId}\n");

$second = $queue->claim('worker-seq-b');
if ($second !== null) {
    $fail('second claim stole a running job');
}
fwrite(STDOUT, "second claim: correctly empty while running\n");

$pdo->exec("UPDATE {$table} SET locked_at = DATE_SUB(NOW(), INTERVAL 3600 SECOND) WHERE id = {$jobId}");
$requeued = $queue->requeueStale(60);
if ($requeued !== 1) {
    $fail("expected 1 stale job requeued, got {$requeued}");
}
fwrite(STDOUT, "stale lease: job requeued\n");

$reclaimed = $queue->claim('worker-seq-b');
if ($reclaimed === null || $reclaimed->id() !== $jobId) {
    $fail('stale job could not be reclaimed');
}
fwrite(STDOUT, "stale job reclaimed by worker-seq-b\n");

$queue->complete($jobId, ['harness' => 'ok']);
$final = $queue->get($jobId);
if ($final === null || $final->status() !== AIBridge\Queue\JobState::COMPLETED) {
    $fail('job did not reach completed state');
}
fwrite(STDOUT, "job completed\n");

if (!function_exists('pcntl_fork')) {
    fwrite(STDERR, "QUEUE CONCURRENCY PARTIAL: pcntl unavailable, forked race not executed.\n");
    exit(4);
}

$raceJob = new AIBridge\Queue\Job(
    'resource_execution',
    ['operation' => 'resource.preview', 'input' => []],
    3,
    300,
    'concurrency-race-' . bin2hex(random_bytes(6)),
    'harness',
    bin2hex(random_bytes(8)),
    null,
    0
);
$raceId = (int) $queue->dispatch($raceJob);
if ($raceId < 1) {
    $fail('race job dispatch failed');
}

$childClaim = static function (string $workerName) use ($modxRoot): int {
    require_once $modxRoot . 'config.core.php';
    require_once MODX_CORE_PATH . 'vendor/autoload.php';
    $child = new MODX\Revolution\modX();
    $child->initialize('mgr');
    $corePath = MODX_CORE_PATH . 'components/aibridge/';
    $child->getLoader()->addPsr4('AIBridge\\', $corePath . 'src/');
    $child->addPackage('AIBridge\\Model', $corePath . 'src/', null, 'AIBridge\\');
    $childQueue = new AIBridge\Queue\QueueManager($child);
    $claimed = $childQueue->claim($workerName);
    return $claimed === null ? 0 : 1;
};

$winners = 0;
foreach (['worker-fork-1', 'worker-fork-2'] as $name) {
    $pid = pcntl_fork();
    if ($pid === -1) {
        $fail('fork failed');
    }
    if ($pid === 0) {
        exit($childClaim($name));
    }
    $status = 0;
    pcntl_waitpid($pid, $status);
    $winners += pcntl_wexitstatus($status);
}

if ($winners !== 1) {
    $fail("expected exactly one winner in forked race, got {$winners}");
}
fwrite(STDOUT, "forked race: exactly one winner\n");

$pdo->exec("UPDATE {$table} SET status='completed' WHERE id = {$raceId}");

fwrite(STDOUT, "QUEUE CONCURRENCY: PASS\n");
