<?php

declare(strict_types=1);

namespace AIBridge\Inspectors;

use MODX\Revolution\modX;

final class TVInspector
{
    public function __construct(private readonly modX $modx)
    {
    }

    public function inspect(): array
    {
        $query = $this->modx->newQuery(\MODX\Revolution\modTemplateVar::class);
        $query->sortby('name', 'ASC');
        $rows = [];
        foreach ($this->modx->getCollection(\MODX\Revolution\modTemplateVar::class, $query) as $tv) {
            $rows[] = [
                'id' => (int) $tv->get('id'),
                'name' => (string) $tv->get('name'),
                'caption' => (string) $tv->get('caption'),
                'description' => (string) $tv->get('description'),
                'type' => (string) $tv->get('type'),
                'default_text' => (string) $tv->get('default_text'),
                'input_properties' => $this->decodeProperties($tv->get('input_properties')),
                'output_properties' => $this->decodeProperties($tv->get('output_properties')),
                'rank' => (int) $tv->get('rank'),
                'elements' => $this->decodeProperties($tv->get('elements')),
            ];
        }
        return $rows;
    }

    private function decodeProperties(mixed $value): array|string|null
    {
        if ($value === null || $value === '') return null;
        if (is_array($value)) return $value;
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : (string) $value;
    }
}
