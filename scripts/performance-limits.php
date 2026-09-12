<?php
declare(strict_types=1);

/**
 * Performance & limits certification harness.
 *
 * Measures Bridge operations against the real MODX/MySQL stack and fails when an
 * observed duration exceeds its threshold. Run inside the MODX container:
 *
 *   MODX_ROOT=/var/www/html php scripts/performance-limits.php
 */

$root = rtrim((string) (getenv('MODX_ROOT') ?: '/var/www/html'), '/') . '/';
if (!is_file($root . 'config.core.php')) {
    fwrite(STDERR, "MODX_ROOT is not a MODX installation: {$root}\n");
    exit(2);
}

require_once $root . 'config.core.php';
require_once MODX_CORE_PATH . 'vendor/autoload.php';

$modx = new \MODX\Revolution\modX();
if (!$modx->initialize('mgr')) {
    fwrite(STDERR, "MODX initialization failed.\n");
    exit(3);
}

$corePath = MODX_CORE_PATH . 'components/aibridge/';
$modx->getLoader()->addPsr4('AIBridge\\', $corePath . 'src/');
$modx->addPackage('AIBridge\\Model', $corePath . 'src/', null, 'AIBridge\\');

require_once dirname(__DIR__) . '/vendor/autoload.php';

$results = [];
function measure(array &$results, string $name, float $elapsedMs, float $thresholdPerOpMs, string $note = '', int $count = 1): void
{
    $perOp = $count > 0 ? $elapsedMs / $count : $elapsedMs;
    $results[] = [
        'name' => $name,
        'total' => round($elapsedMs, 2),
        'per_op' => round($perOp, 2),
        'threshold' => $thresholdPerOpMs,
        'ok' => $perOp <= $thresholdPerOpMs,
        'note' => $note,
    ];
}
function msSince(int $startNs): float
{
    return (hrtime(true) - $startNs) / 1_000_000;
}

$suffix = bin2hex(random_bytes(4));
$profileId = (int) ((new \AIBridge\MultiSite\ProfileService($modx))->create([
    'name' => 'Perf ' . $suffix,
    'site_key' => 'perf-' . $suffix,
])->toArray()['id'] ?? 0);

// --- Large resource / HTML content -----------------------------------------
$template = $modx->newObject(\MODX\Revolution\modTemplate::class);
$template->fromArray(['templatename' => 'PerfTemplate ' . $suffix, 'content' => '[[*content]]', 'createdon' => time(), 'editedon' => time()]);
$template->save();
$templateId = (int) $template->get('id');

$bigContent = '<h1>Perf</h1>' . str_repeat('<p>' . str_repeat('x', 1024) . '</p>', 256); // ~256 KiB
$resource = $modx->newObject(\MODX\Revolution\modResource::class);
$resource->fromArray([
    'pagetitle' => 'Perf ' . $suffix,
    'alias' => 'perf-' . $suffix,
    'template' => $templateId,
    'content' => $bigContent,
    'published' => 0,
    'deleted' => 0,
    'hidemenu' => 1,
    'context_key' => 'web',
    'createdon' => time(),
    'editedon' => time(),
]);
$t = hrtime(true);
$resource->save();
measure($results, 'large_content_save (256 KiB)', msSince($t), 2000, 'bytes=' . strlen($bigContent));
$resourceId = (int) $resource->get('id');

// --- Many Template Variables ------------------------------------------------
$tvIds = [];
for ($i = 0; $i < 50; $i++) {
    $tv = $modx->newObject(\MODX\Revolution\modTemplateVar::class);
    $tv->fromArray(['name' => 'perf_tv_' . $suffix . '_' . $i, 'caption' => 'Perf ' . $i, 'type' => 'text', 'default_text' => '']);
    $tv->save();
    $link = $modx->newObject(\MODX\Revolution\modTemplateVarTemplate::class);
    $link->set('tmplvarid', (int) $tv->get('id'));
    $link->set('templateid', $templateId);
    $link->set('rank', $i);
    $link->save();
    $tvIds[] = (int) $tv->get('id');
}
$resource = $modx->getObject(\MODX\Revolution\modResource::class, $resourceId);
$t = hrtime(true);
foreach ($tvIds as $i => $tvId) {
    $resource->setTVValue($tvId, 'value-' . $i);
}
$resource->save();
measure($results, 'many_tvs_save (50 TVs)', msSince($t), 3000);

$modx->getCacheManager()->refresh();
$resource = $modx->getObject(\MODX\Revolution\modResource::class, $resourceId);
$t = hrtime(true);
(new \AIBridge\Services\SnapshotService($modx))->createForResource($resource, 'resource.update', $profileId);
measure($results, 'snapshot (256 KiB + 50 TVs)', msSince($t), 2000);

// --- Queue depth ------------------------------------------------------------
$queue = new \AIBridge\Queue\QueueManager($modx);
$jobIds = [];
$t = hrtime(true);
for ($i = 0; $i < 200; $i++) {
    $jobIds[] = (int) $queue->dispatch(new \AIBridge\Queue\Job(
        'resource_execution',
        ['operation' => 'resource.preview', 'input' => ['id' => $resourceId], 'principal' => ['type' => 'manager', 'manager_authorized' => true, 'profile_id' => $profileId], 'request' => ['ip' => 'manager', 'channel' => 'manager']],
        3,
        300,
        'perf-' . $suffix . '-' . $i,
        'perf',
        null,
        null,
        $profileId
    ));
}
measure($results, 'queue_dispatch (200 jobs)', msSince($t), 50, '200 jobs', 200);

$claimed = [];
$t = hrtime(true);
while (true) {
    $record = $queue->claim('perf-worker-' . $suffix);
    if ($record === null) {
        break;
    }
    $claimed[] = (int) $record->id();
}
measure($results, 'queue_claim (200 jobs)', msSince($t), 50, 'claimed=' . count($claimed), max(1, count($claimed)));
foreach ($claimed as $id) {
    $queue->cancel($id, 'perf_cleanup');
}

// --- Rate limiter -----------------------------------------------------------
$limiter = new \AIBridge\Security\RateLimiter($modx);
$t = hrtime(true);
$allowed = 0;
for ($i = 0; $i < 500; $i++) {
    $result = $limiter->consume('perf:' . $suffix . ':' . intdiv($i, 100), 1000, 60, $profileId);
    if ($result['allowed']) {
        $allowed++;
    }
}
measure($results, 'rate_limiter (500 consumes)', msSince($t), 50, 'allowed=' . $allowed, 500);

// --- Audit growth -----------------------------------------------------------
$audit = new \AIBridge\Audit\AuditService($modx);
$t = hrtime(true);
for ($i = 0; $i < 500; $i++) {
    $audit->record('perf_probe', ['profile_id' => $profileId, 'resource_id' => $resourceId, 'operation' => 'perf', 'note' => 'probe-' . $i]);
}
measure($results, 'audit_record (500 events)', msSince($t), 50, '', 500);

$t = hrtime(true);
$modx->getCollection(\AIBridge\Model\AuditEvent::class, ['event' => 'perf_probe', 'profile_id' => $profileId], ['limit' => 100, 'sortby' => 'created_at', 'sortdir' => 'DESC']);
measure($results, 'audit_read (100 events)', msSince($t), 2000);

// --- Cleanup ----------------------------------------------------------------
foreach ($modx->getCollection(\AIBridge\Model\AuditEvent::class, ['event' => 'perf_probe', 'profile_id' => $profileId]) ?: [] as $event) {
    $event->remove();
}
foreach ($tvIds as $tvId) {
    foreach ($modx->getCollection(\MODX\Revolution\modTemplateVarTemplate::class, ['tmplvarid' => $tvId]) ?: [] as $link) {
        $link->remove();
    }
    $tv = $modx->getObject(\MODX\Revolution\modTemplateVar::class, $tvId);
    if ($tv) {
        $tv->remove();
    }
}
$resource = $modx->getObject(\MODX\Revolution\modResource::class, $resourceId);
if ($resource) {
    $resource->remove();
}
$template->remove();

// --- Report -----------------------------------------------------------------
$failed = [];
echo str_pad('measurement', 34) . str_pad('total', 13) . str_pad('per-op', 13) . str_pad('thr/op', 11) . "status\n";
foreach ($results as $r) {
    echo str_pad($r['name'], 34) . str_pad($r['total'] . ' ms', 13) . str_pad($r['per_op'] . ' ms', 13) . str_pad($r['threshold'] . ' ms', 11) . ($r['ok'] ? 'PASS' : 'FAIL')
        . ($r['note'] !== '' ? ' (' . $r['note'] . ')' : '') . "\n";
    if (!$r['ok']) {
        $failed[] = $r['name'];
    }
}

if ($failed !== []) {
    fwrite(STDERR, 'PERFORMANCE: FAILED (' . implode(', ', $failed) . ")\n");
    exit(1);
}
echo "PERFORMANCE & LIMITS: PASS\n";
