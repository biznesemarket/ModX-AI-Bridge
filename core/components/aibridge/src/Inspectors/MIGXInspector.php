<?php

declare(strict_types=1);

namespace AIBridge\Inspectors;

use MODX\Revolution\modX;

final class MIGXInspector
{
    public function __construct(private readonly modX $modx)
    {
    }

    public function inspect(array $tvs): array
    {
        $detected = [];
        foreach ($tvs as $tv) {
            $properties = $tv['input_properties'] ?? null;
            $text = is_array($properties) ? json_encode($properties) : (string) $properties;
            if (stripos((string) $text, 'migx') !== false) {
                $detected[] = [
                    'tv_id' => $tv['id'],
                    'tv_name' => $tv['name'],
                    'detected' => true,
                ];
            }
        }
        return [
            'available' => $this->modx->getService('migx') !== null,
            'tv_bindings' => $detected,
        ];
    }
}
