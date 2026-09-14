<?php

declare(strict_types=1);

namespace AIBridge\Queue;

use AIBridge\Audit\AuditService;
use AIBridge\Security\SecretRedactor;

final class Worker
{
    public function __construct(
        private readonly QueueManager $queue,
        private readonly JobRegistry $registry,
        private readonly ?AuditService $audit = null,
        private readonly SecretRedactor $redactor = new SecretRedactor(),
    ) {}

    public function runOnce(string $workerId): ?array
    {
        $job = $this->queue->claim($workerId);
        if (!$job) return null;
        $started = microtime(true);
        $deadline = new JobDeadline($started + max(1, $job->timeoutSeconds()));
        try {
            $handler = $this->registry->get($job->type());
            $result = $handler->handle($job, new JobContext($this->queue, $job, $deadline));
            $elapsed = microtime(true) - $started;
            if ($elapsed > $job->timeoutSeconds()) {
                throw new JobTimeoutException('Job exceeded timeout of ' . $job->timeoutSeconds() . ' seconds.');
            }
            $safe = $this->redactor->redactRecursive($result);
            $this->queue->complete($job->id(), $safe);
            $this->audit?->record('job_completed', ['profile_id' => (int) ($job->raw()['profile_id'] ?? 0), 'job_id' => $job->id(), 'type' => $job->type(), 'duration_ms' => (int) round($elapsed * 1000), 'request_id' => (string) ($job->raw()['request_id'] ?? '')]);
            return ['id' => $job->id(), 'status' => JobState::COMPLETED, 'result' => $safe];
        } catch (\Throwable $e) {
            // `JobTimeoutException` extends `NonRetryableJobException`: partial
            // work may already be committed, so a timeout is never requeued.
            $retryable = !($e instanceof NonRetryableJobException);
            $timeout = $e instanceof JobTimeoutException;
            $error = [
                'code' => $timeout ? 'job_timeout' : 'job_failed',
                'message' => $timeout ? 'Job exceeded its time budget.' : 'Job execution failed.',
            ];
            $status = $this->queue->fail($job->id(), $this->redactor->redactRecursive($error), $retryable);
            $this->audit?->record('job_failed', ['profile_id' => (int) ($job->raw()['profile_id'] ?? 0), 'job_id' => $job->id(), 'type' => $job->type(), 'status' => $status, 'error' => $error, 'diagnostic' => $this->redactor->redactText($e->getMessage()), 'request_id' => (string) ($job->raw()['request_id'] ?? '')]);
            return ['id' => $job->id(), 'status' => $status, 'error' => $error];
        }
    }
}
