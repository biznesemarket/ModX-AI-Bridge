<?php

declare(strict_types=1);

namespace AIBridge\Queue;

final class JobContext
{
    public function __construct(
        private readonly QueueManager $queue,
        private readonly JobRecord $job,
    ) {}

    public function progress(int $percent, array $meta = []): void
    {
        $this->queue->updateProgress($this->job->id(), $percent, $meta);
    }

    public function heartbeat(): void
    {
        $this->queue->heartbeat($this->job->id());
    }
}
