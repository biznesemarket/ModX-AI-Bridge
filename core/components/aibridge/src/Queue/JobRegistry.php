<?php

declare(strict_types=1);

namespace AIBridge\Queue;

final class JobRegistry
{
    /** @var array<string,JobHandler> */
    private array $handlers = [];

    public function register(string $type, JobHandler $handler): void
    {
        if ($type === '') throw new \InvalidArgumentException('Job type cannot be empty.');
        $this->handlers[$type] = $handler;
    }

    public function get(string $type): JobHandler
    {
        if (!isset($this->handlers[$type])) {
            throw new \RuntimeException('No handler registered for job type: ' . $type);
        }
        return $this->handlers[$type];
    }

    public function types(): array { return array_keys($this->handlers); }
}
