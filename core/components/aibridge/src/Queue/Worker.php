<?php

declare(strict_types=1);

namespace AIBridge\Queue;

use AIBridge\Audit\AuditService;
use AIBridge\Security\IdempotencyService;
use AIBridge\Security\SecretRedactor;

final class Worker
{
    public function __construct(
        private readonly QueueManager $queue,
        private readonly JobRegistry $registry,
        private readonly ?AuditService $audit = null,
        private readonly SecretRedactor $redactor = new SecretRedactor(),
        private readonly ?IdempotencyService $idempotency = null,
    ) {}

    public function runOnce(string $workerId): ?array
    {
        $job = $this->queue->claim($workerId);
        if (!$job) return null;
        return $this->runJob($job);
    }

    /**
     * Execute one already-claimed job in the current process.
     *
     * Used directly by the inline worker mode and by the supervised child
     * process (`--exec-job=<id>`), which owns a fresh MODX/database connection.
     */
    public function runJob(JobRecord $job): array
    {
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

    /**
     * Claim one job and execute it in a child process with a hard wall-clock
     * budget.
     *
     * The child owns its own MODX/database connection, so terminating it closes
     * that connection and rolls back an open transaction server-side — the
     * cooperative `JobDeadline` checks cannot preempt a long statement on their
     * own. On expiry the job is marked as a non-retryable `job_timeout` and its
     * idempotency key is released (only an `in_progress` key is abandoned), so a
     * pre-mutation abort stays retryable while a timeout is never requeued.
     *
     * Falls back to inline execution when the platform cannot spawn processes.
     *
     * The child identifies the claimed job through the `AIBRIDGE_EXEC_JOB`
     * environment variable, so a runner command never has to accept extra argv.
     *
     * @param list<string> $runnerCommand Base argv for the child runner.
     */
    public function runOnceSupervised(string $workerId, array $runnerCommand): ?array
    {
        $job = $this->queue->claim($workerId);
        if (!$job) return null;

        if (!function_exists('proc_open') || $runnerCommand === []) {
            return $this->runJob($job);
        }

        $nullDevice = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
        $descriptors = [
            0 => ['file', $nullDevice, 'r'],
            1 => ['file', $nullDevice, 'w'],
            2 => ['pipe', 'w'],
        ];
        $environment = array_merge(getenv() ?: [], ['AIBRIDGE_EXEC_JOB' => (string) $job->id()]);
        $process = proc_open($runnerCommand, $descriptors, $pipes, null, $environment);
        if (!is_resource($process)) {
            return $this->runJob($job);
        }

        $stderr = '';
        $drain = function () use (&$pipes, &$stderr): void {
            if (!isset($pipes[2]) || !is_resource($pipes[2])) return;
            stream_set_blocking($pipes[2], false);
            $chunk = stream_get_contents($pipes[2]);
            if (is_string($chunk) && $chunk !== '') {
                $stderr = substr($stderr . $chunk, -4096);
            }
        };

        $started = microtime(true);
        $budget = max(1, $job->timeoutSeconds());
        $timedOut = false;
        while (true) {
            $status = proc_get_status($process);
            if (!($status['running'] ?? false)) {
                break;
            }
            if ((microtime(true) - $started) >= $budget) {
                $timedOut = true;
                // Signal 9 is SIGKILL on Unix and an unconditional terminate on Windows.
                proc_terminate($process, 9);
                break;
            }
            $drain();
            usleep(200000);
        }
        $drain();
        if (isset($pipes[2]) && is_resource($pipes[2])) {
            fclose($pipes[2]);
        }
        proc_close($process);

        $current = $this->queue->get($job->id());
        if ($timedOut) {
            return $this->failHardTimeout($job, $current, $stderr);
        }
        if ($current !== null && $current->status() !== JobState::RUNNING) {
            return ['id' => $job->id(), 'status' => $current->status()];
        }
        // The child exited without settling the job (fatal error or external kill).
        return $this->failStalled($job, $stderr);
    }

    private function failHardTimeout(JobRecord $job, ?JobRecord $current, string $stderr = ''): array
    {
        if ($current !== null && $current->status() !== JobState::RUNNING) {
            return ['id' => $job->id(), 'status' => $current->status()];
        }
        $error = ['code' => 'job_timeout', 'message' => 'Job exceeded its time budget.'];
        $status = $this->queue->fail($job->id(), $error, false);
        $this->abandonIdempotency($job);
        $diagnostic = 'supervisor terminated the job process after ' . max(1, $job->timeoutSeconds()) . ' second(s)';
        if (trim($stderr) !== '') {
            $diagnostic .= '; child stderr: ' . $this->redactor->redactText($stderr);
        }
        $this->audit?->record('job_failed', [
            'profile_id' => $job->profileId(),
            'job_id' => $job->id(),
            'type' => $job->type(),
            'status' => $status,
            'error' => $error,
            'diagnostic' => $diagnostic,
            'request_id' => (string) ($job->raw()['request_id'] ?? ''),
        ]);
        return ['id' => $job->id(), 'status' => $status, 'error' => $error];
    }

    private function failStalled(JobRecord $job, string $stderr = ''): array
    {
        $error = ['code' => 'job_failed', 'message' => 'Job execution failed.'];
        $status = $this->queue->fail($job->id(), $error, true);
        $diagnostic = 'supervised job process exited without settling the job';
        if (trim($stderr) !== '') {
            $diagnostic .= '; child stderr: ' . $this->redactor->redactText($stderr);
        }
        $this->audit?->record('job_failed', [
            'profile_id' => $job->profileId(),
            'job_id' => $job->id(),
            'type' => $job->type(),
            'status' => $status,
            'error' => $error,
            'diagnostic' => $diagnostic,
            'request_id' => (string) ($job->raw()['request_id'] ?? ''),
        ]);
        return ['id' => $job->id(), 'status' => $status, 'error' => $error];
    }

    /**
     * Release an in-progress idempotency key after a hard timeout. The child
     * process had its own connection, so a killed transaction is rolled back by
     * the database; the key must not stay poisoned for the next attempt.
     */
    private function abandonIdempotency(JobRecord $job): void
    {
        if ($this->idempotency === null) return;
        $key = trim((string) ($job->raw()['idempotency_key'] ?? ''));
        $principal = (string) ($job->raw()['principal_id'] ?? '');
        if ($key === '' || $principal === '') return;
        $payload = $job->payload();
        $operation = (string) ($payload['operation'] ?? $job->type());
        try {
            $this->idempotency->abandon($key, $principal, $operation, $job->profileId());
        } catch (\Throwable) {
            // The job is already terminal; releasing the key is best effort.
        }
    }
}
