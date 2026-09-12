<?php

declare(strict_types=1);

namespace AIBridge\Processors;

use AIBridge\Services\SiteFingerprintService;

final class SiteFingerprintProcessor
{
    public function process(array $payload = []): array
    {
        return (new SiteFingerprintService())->fingerprint($payload);
    }
}
