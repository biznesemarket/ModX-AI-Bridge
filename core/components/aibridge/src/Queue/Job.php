<?php

declare(strict_types=1);

namespace AIBridge\Queue;

final class Job
{
    public function __construct(
        public readonly string $type,
        public readonly array $payload = [],
        public readonly int $maxAttempts = 3,
        public readonly int $timeoutSeconds = 300,
        public readonly ?string $idempotencyKey = null,
        public readonly ?string $principalId = null,
        public readonly ?string $requestId = null,
        public readonly ?string $availableAt = null,
        public readonly ?int $profileId = null,
    ) {
        if ($this->type === '') {
            throw new \InvalidArgumentException('Job type cannot be empty.');
        }
        if ($this->maxAttempts < 1) {
            throw new \InvalidArgumentException('maxAttempts must be >= 1.');
        }
        if ($this->timeoutSeconds < 1) {
            throw new \InvalidArgumentException('timeoutSeconds must be >= 1.');
        }
    }
}
