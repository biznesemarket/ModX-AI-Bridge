<?php

declare(strict_types=1);

namespace AIBridge\Processors;

use AIBridge\Services\SchemaService;
use AIBridge\Services\SiteIntelligenceService;
use MODX\Revolution\modX;

final class SiteSchemaProcessor
{
    public function __construct(private readonly modX $modx)
    {
    }

    public function process(array $payload = []): array
    {
        $discovered = (new SiteIntelligenceService($this->modx))->discover($payload);
        return (new SchemaService())->build(new \AIBridge\Contracts\DiscoveredSite(
            $discovered['contract_version'],
            $discovered['contract']
        ));
    }
}
