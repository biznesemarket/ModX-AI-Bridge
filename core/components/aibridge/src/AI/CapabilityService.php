<?php
declare(strict_types=1);
namespace AIBridge\AI;

use MODX\Revolution\modX;

final class CapabilityService
{
    public function __construct(
        private readonly CapabilityCatalog $catalog = new CapabilityCatalog(),
        private readonly ?modX $modx = null
    ) {}

    public function manifest(): array
    {
        $manifest = [
            'version' => '1.0',
            'product' => 'modx-ai-bridge',
            'capabilities' => $this->catalog->all(),
            'interfaces' => ['rest' => ['version' => 'v2'], 'mcp' => ['protocolVersion' => '2025-06-18']],
        ];
        if ($this->modx !== null) {
            $manifest['contexts'] = $this->contexts();
        }
        return $manifest;
    }

    /**
     * Available MODX contexts, so clients know the valid `context_key` filter
     * values for the resource list and site schema.
     */
    private function contexts(): array
    {
        $contexts = [];
        foreach ($this->modx->getCollection(\MODX\Revolution\modContext::class) ?: [] as $context) {
            $contexts[] = [
                'key' => (string) $context->get('key'),
                'name' => $context->get('name') !== null ? (string) $context->get('name') : null,
                'description' => $context->get('description') !== null ? (string) $context->get('description') : null,
            ];
        }
        usort($contexts, static fn (array $a, array $b): int => strcmp($a['key'], $b['key']));
        return $contexts;
    }
}
