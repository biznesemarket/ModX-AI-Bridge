<?php
declare(strict_types=1);

/**
 * AIBridge queue worker CLI entrypoint.
 *
 * Usage:
 *   MODX_ROOT=/path/to/modx php core/components/aibridge/worker.php [--once] [--sleep=N] [--max-iterations=N]
 *
 * Options:
 *   --once             process at most one job and exit;
 *   --sleep=N          idle sleep in seconds (default 5);
 *   --max-iterations=N stop after N loop iterations.
 *
 * Graceful shutdown: SIGTERM/SIGINT stop claiming new jobs; a claimed job is
 * allowed to finish or expire its lease. Stale jobs are requeued while idle.
 */

$modxRoot = rtrim((string) (getenv('MODX_ROOT') ?: '/var/www/html'), '/') . '/';
if (!is_file($modxRoot . 'config.core.php')) {
    fwrite(STDERR, "MODX_ROOT is not a MODX installation: {$modxRoot}\n");
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
if (!is_dir($corePath)) {
    fwrite(STDERR, "AIBridge component is not installed: {$corePath}\n");
    exit(4);
}

$modx->getLoader()->addPsr4('AIBridge\\', $corePath . 'src/');
$modx->addPackage('AIBridge\\Model', $corePath . 'src/', null, 'AIBridge\\');

$options = getopt('', ['once', 'sleep::', 'max-iterations::']);
$once = isset($options['once']);
$sleep = max(1, (int) ($options['sleep'] ?? 5));
$maxIterations = isset($options['max-iterations']) ? max(1, (int) $options['max-iterations']) : 0;

$queue = new AIBridge\Queue\QueueManager($modx);
$registry = new AIBridge\Queue\JobRegistry();
$registry->register('resource_execution', new AIBridge\Queue\ResourceExecutionJobHandler($modx));
$worker = new AIBridge\Queue\Worker($queue, $registry, new AIBridge\Audit\AuditService($modx));

$workerId = 'worker-' . getmypid() . '-' . substr(bin2hex(random_bytes(4)), 0, 8);

$running = true;
$stop = function () use (&$running): void {
    $running = false;
};
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, $stop);
    pcntl_signal(SIGINT, $stop);
}

fwrite(STDOUT, "[worker] started {$workerId}\n");

$iterations = 0;
while ($running) {
    $result = null;
    try {
        $result = $worker->runOnce($workerId);
    } catch (\Throwable $e) {
        fwrite(STDERR, '[worker] error: ' . $e->getMessage() . "\n");
    }
    $iterations++;

    if ($once) {
        break;
    }
    if ($maxIterations > 0 && $iterations >= $maxIterations) {
        break;
    }
    if ($result === null) {
        try {
            $requeued = $queue->requeueStale();
            if ($requeued > 0) {
                fwrite(STDOUT, "[worker] requeued {$requeued} stale job(s)\n");
            }
        } catch (\Throwable $e) {
            fwrite(STDERR, '[worker] requeue error: ' . $e->getMessage() . "\n");
        }
        if ($running) {
            sleep($sleep);
        }
    }
}

fwrite(STDOUT, "[worker] stopped after {$iterations} iteration(s)\n");
