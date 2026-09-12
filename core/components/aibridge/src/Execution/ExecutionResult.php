<?php

declare(strict_types=1);

namespace AIBridge\Execution;

final class ExecutionResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $operation,
        public readonly array $data = [],
        public readonly array $warnings = [],
        public readonly ?string $errorCode = null,
        public readonly ?string $message = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'success' => $this->success,
            'operation' => $this->operation,
            'data' => $this->data,
            'warnings' => $this->warnings,
            'error' => $this->errorCode ? ['code' => $this->errorCode, 'message' => $this->message] : null,
        ], static fn($v) => $v !== null);
    }
}
