<?php

declare(strict_types=1);

namespace AIBridge\Security;

final class SecurityDecision
{
    public function __construct(public readonly bool $allowed, public readonly string $code, public readonly array $reasons = [], public readonly array $meta = []) {}
    public function allowed(): bool { return $this->allowed; }
    public function code(): string { return $this->code; }
    public function reasons(): array { return $this->reasons; }
    public function toArray(): array { return ['allowed'=>$this->allowed,'code'=>$this->code,'reasons'=>$this->reasons,'meta'=>$this->meta]; }
}
