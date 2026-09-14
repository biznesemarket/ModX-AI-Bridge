<?php

declare(strict_types=1);

namespace AIBridge\Queue;

/**
 * Per-attempt wall-clock deadline for a job.
 *
 * Handlers and the execution service consult this cooperatively so a job can
 * stop before starting another side effect when its time budget is already
 * exhausted. It does not preempt a running statement; combined with the
 * non-retryable timeout status it prevents a timed-out mutation from being
 * replayed by the retry queue.
 */
final class JobDeadline
{
    public function __construct(private readonly float $deadlineAt) {}

    public function at(): float
    {
        return $this->deadlineAt;
    }

    public function exceeded(): bool
    {
        return $this->deadlineAt > 0.0 && microtime(true) >= $this->deadlineAt;
    }

    public function remainingSeconds(): int
    {
        if ($this->deadlineAt <= 0.0) return 0;
        return max(0, (int) ceil($this->deadlineAt - microtime(true)));
    }
}
