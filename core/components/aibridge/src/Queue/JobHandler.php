<?php

declare(strict_types=1);

namespace AIBridge\Queue;

interface JobHandler
{
    /** @return array<string,mixed> */
    public function handle(JobRecord $job, JobContext $context): array;
}
