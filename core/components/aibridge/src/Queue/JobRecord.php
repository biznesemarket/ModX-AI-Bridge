<?php

declare(strict_types=1);

namespace AIBridge\Queue;

final class JobRecord
{
    public function __construct(private readonly array $data) {}

    public function profileId(): int { return (int) ($this->data['profile_id'] ?? 0); }
    public function id(): int { return (int) ($this->data['id'] ?? 0); }
    public function type(): string { return (string) ($this->data['type'] ?? ''); }
    public function status(): string { return (string) ($this->data['status'] ?? ''); }
    public function attempts(): int { return (int) ($this->data['attempts'] ?? 0); }
    public function maxAttempts(): int { return (int) ($this->data['max_attempts'] ?? 3); }
    public function timeoutSeconds(): int { return (int) ($this->data['timeout_seconds'] ?? 300); }
    public function payload(): array { return $this->decode((string) ($this->data['payload_json'] ?? '{}')); }
    public function raw(): array { return $this->data; }

    private function decode(string $json): array
    {
        if ($json === '') return [];
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}
