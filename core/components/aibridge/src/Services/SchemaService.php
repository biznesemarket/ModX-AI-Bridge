<?php

declare(strict_types=1);

namespace AIBridge\Services;

use AIBridge\Contracts\SiteContract;

final class SchemaService
{
    public function build(SiteContract $contract): array
    {
        $data = $contract->toArray();
        return [
            'contract' => 'modx-ai-bridge/schema',
            'version' => '1.0',
            'site_contract_version' => $contract->version(),
            'fingerprint' => $contract->hash(),
            'capabilities' => [
                'templates' => count($data['templates'] ?? []),
                'template_variables' => count($data['template_variables'] ?? []),
                'chunks' => count($data['chunks'] ?? []),
                'snippets' => count($data['snippets'] ?? []),
                'resources' => count($data['resources'] ?? []),
                'migx' => $data['migx']['available'] ?? false,
            ],
            'contract' => $data,
        ];
    }
}
