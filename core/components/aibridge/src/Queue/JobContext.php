<?php

declare(strict_types=1);

namespace AIBridge\Queue;

final class JobContext
{
    public function __construct(
        private readonly QueueManager $queue,
        private readonly JobRecord $job,
        private readonly ?JobDeadline $deadline = null,
    ) {}

    public function progress(int $percent, array $meta = []): void
    {
        $this->queue->updateProgress($this->job->id(), $percent, $meta);
    }

    public function heartbeat(): void
    {
        $this->queue->heartbeat($this->job->id());
    }

    public function deadline(): ?JobDeadline
    {
        return $this->deadline;
    }

    /**
     * Fail fast before the next side effect when the job time budget is spent.
     */
    public function ensureWithinDeadline(): void
    {
        if ($this->deadline !== null && $this->deadline->exceeded()) {
            throw new JobTimeoutException('Job exceeded its time budget before the next execution step.');
        }
    }
}
