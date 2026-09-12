<?php

declare(strict_types=1);

namespace AIBridge\Services;

use AIBridge\Security\SecretRedactor;
use MODX\Revolution\modX;

final class SnapshotService
{
    public function __construct(private readonly modX $modx, private readonly SecretRedactor $redactor = new SecretRedactor()) {}

    public function createForResource(\xPDOObject $resource, string $operation, int $profileId = 0): array
    {
        $data = $resource->toArray();
        $data['__aibridge'] = [
            'template' => (int) $resource->get('template'),
            'properties' => $resource->get('properties'),
            'tvs' => $this->getTvValues($resource),
        ];
        $data = $this->redactor->redactRecursive($data);
        $row = $this->modx->newObject(\AIBridge\Model\Snapshot::class);
        $row->fromArray([
            'profile_id' => $profileId,
            'resource_id' => (int) $resource->get('id'),
            'operation' => $operation,
            'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        if (!$row->save()) throw new \RuntimeException('Snapshot persistence failed.');
        return ['id' => (int) $row->get('id'), 'resource_id' => (int) $resource->get('id')];
    }

    private function getTvValues(\xPDOObject $resource): array
    {
        $values = [];
        $tvs = $resource->getMany('TemplateVars');
        if (is_array($tvs)) foreach ($tvs as $tv) {
            if (method_exists($tv, 'get')) $values[(string) $tv->get('name')] = $tv->getValue($resource->get('id'));
        }
        return $values;
    }
}
