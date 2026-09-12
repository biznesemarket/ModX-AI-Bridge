<?php

declare(strict_types=1);

namespace AIBridge\Queue;

use MODX\Revolution\modX;

final class QueueManager
{
    public function __construct(private readonly modX $modx) {}

    public function dispatch(Job $job): string
    {
        $object = $this->modx->newObject(\AIBridge\Model\Job::class);
        $object->fromArray([
            'profile_id' => $job->profileId ?? 0,
            'type' => $job->type,
            'status' => JobState::QUEUED,
            'payload_json' => json_encode($job->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'attempts' => 0,
            'max_attempts' => $job->maxAttempts,
            'timeout_seconds' => $job->timeoutSeconds,
            'available_at' => $job->availableAt ?: date('Y-m-d H:i:s'),
            'idempotency_key' => $job->idempotencyKey,
            'principal_id' => $job->principalId,
            'request_id' => $job->requestId,
            'progress' => 0,
            'progress_json' => '{}',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        if (!$object->save()) throw new \RuntimeException('Job persistence failed.');
        return (string) $object->get('id');
    }

    public function get(int $id): ?JobRecord
    {
        $object = $this->modx->getObject(\AIBridge\Model\Job::class, $id);
        return $object ? new JobRecord($object->toArray()) : null;
    }

    /** Atomically claims one eligible job using a row lock. */
    public function claim(string $workerId): ?JobRecord
    {
        $pdo = $this->pdo();
        $table = $this->table();
        $pdo->beginTransaction();
        try {
            $sql = "SELECT * FROM {$table} WHERE status = 'queued' AND available_at <= NOW() ORDER BY id ASC LIMIT 1 FOR UPDATE";
            $row = $pdo->query($sql)->fetch(\PDO::FETCH_ASSOC);
            if (!$row) { $pdo->commit(); return null; }
            $stmt = $pdo->prepare("UPDATE {$table} SET status='running', attempts=attempts+1, started_at=COALESCE(started_at,NOW()), locked_at=NOW(), locked_by=:worker WHERE id=:id AND status='queued'");
            $stmt->execute(['worker' => $workerId, 'id' => (int) $row['id']]);
            if ($stmt->rowCount() !== 1) { $pdo->rollBack(); return null; }
            $pdo->commit();
            $row['status'] = JobState::RUNNING;
            $row['attempts'] = (int) $row['attempts'] + 1;
            $row['locked_by'] = $workerId;
            return new JobRecord($row);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function updateProgress(int $id, int $percent, array $meta = []): void
    {
        $percent = max(0, min(100, $percent));
        $stmt = $this->pdo()->prepare('UPDATE ' . $this->table() . ' SET progress=:progress, progress_json=:meta, locked_at=NOW() WHERE id=:id AND status=\'running\'');
        $stmt->execute(['progress' => $percent, 'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 'id' => $id]);
    }

    public function heartbeat(int $id): void
    {
        $stmt = $this->pdo()->prepare('UPDATE ' . $this->table() . ' SET locked_at=NOW() WHERE id=:id AND status=\'running\'');
        $stmt->execute(['id' => $id]);
    }

    public function complete(int $id, array $result): void
    {
        $stmt = $this->pdo()->prepare('UPDATE ' . $this->table() . ' SET status=\'completed\', progress=100, result_json=:result, finished_at=NOW(), locked_at=NULL, locked_by=NULL WHERE id=:id AND status=\'running\'');
        $stmt->execute(['result' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 'id' => $id]);
    }

    public function fail(int $id, array $error, bool $retryable = true): string
    {
        $job = $this->get($id);
        if (!$job) throw new \RuntimeException('Job not found.');
        $retry = $retryable && $job->attempts() < $job->maxAttempts();
        $status = $retry ? JobState::QUEUED : JobState::FAILED;
        $available = $retry ? date('Y-m-d H:i:s', time() + $this->backoffSeconds($job->attempts())) : date('Y-m-d H:i:s');
        $stmt = $this->pdo()->prepare('UPDATE ' . $this->table() . ' SET status=:status, error_json=:error, available_at=:available_at, finished_at=:finished_at, locked_at=NULL, locked_by=NULL WHERE id=:id AND status=\'running\'');
        $stmt->execute(['status' => $status, 'error' => json_encode($error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 'available_at' => $available, 'finished_at' => $retry ? null : date('Y-m-d H:i:s'), 'id' => $id]);
        return $status;
    }

    public function cancel(int $id, string $reason = 'cancelled'): bool
    {
        $stmt = $this->pdo()->prepare('UPDATE ' . $this->table() . ' SET status=\'cancelled\', error_json=:error, finished_at=NOW(), locked_at=NULL, locked_by=NULL WHERE id=:id AND status IN (\'queued\',\'running\')');
        $stmt->execute(['error' => json_encode(['code' => 'cancelled', 'message' => $reason], JSON_UNESCAPED_UNICODE), 'id' => $id]);
        return $stmt->rowCount() === 1;
    }

    /** Requeues abandoned workers after a lease timeout. */
    public function requeueStale(int $leaseSeconds = 900): int
    {
        $leaseSeconds = max(1, $leaseSeconds);
        $stmt = $this->pdo()->prepare('UPDATE ' . $this->table() . ' SET status=\'queued\', available_at=NOW(), locked_at=NULL, locked_by=NULL WHERE status=\'running\' AND locked_at < DATE_SUB(NOW(), INTERVAL :seconds SECOND)');
        $stmt->bindValue(':seconds', $leaseSeconds, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    private function backoffSeconds(int $attempt): int { return min(3600, 2 ** max(0, $attempt - 1)); }

    private function pdo(): \PDO
    {
        $pdo = $this->modx->getConnection();
        if (!$pdo instanceof \PDO) throw new \RuntimeException('MODX database connection unavailable.');
        return $pdo;
    }

    private function table(): string
    {
        return $this->modx->getTableName(\AIBridge\Model\Job::class);
    }
}
