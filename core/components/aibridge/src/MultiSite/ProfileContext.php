<?php
declare(strict_types=1);
namespace AIBridge\MultiSite;

final class ProfileContext
{
    public function __construct(
        public readonly int $profileId,
        public readonly string $siteKey,
        public readonly string $name,
        public readonly string $environment,
        public readonly string $status = 'active',
        public readonly ?string $baseUrl = null,
        public readonly array $metadata = [],
    ) {}

    public function active(): bool { return $this->status === 'active'; }

    public function toArray(): array {
        return ['id'=>$this->profileId,'site_key'=>$this->siteKey,'name'=>$this->name,'environment'=>$this->environment,'status'=>$this->status,'base_url'=>$this->baseUrl,'metadata'=>$this->metadata];
    }
}
