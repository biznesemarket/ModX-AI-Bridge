<?php

declare(strict_types=1);

namespace AIBridge\Services;

use MODX\Revolution\modX;

/**
 * Read-back access to a persisted MODX resource.
 *
 * Returns a stable, whitelisted projection so AI clients can verify the result
 * of a queued mutation without direct database access. Template variables bound
 * to the resource template are exposed read-only under the `tvs` map.
 */
final class ResourceReadService
{
    private const STRING_FIELDS = [
        'pagetitle', 'longtitle', 'description', 'introtext', 'content', 'alias',
        'class_key', 'context_key', 'publishedon', 'createdon', 'editedon',
    ];
    private const INT_FIELDS = ['id', 'parent', 'template', 'menuindex'];
    private const BOOL_FIELDS = ['published', 'hidemenu', 'deleted', 'searchable'];

    public function __construct(private readonly modX $modx) {}

    public function read(int $id): array
    {
        if ($id < 1) {
            return $this->notFound();
        }

        $resource = $this->modx->getObject(\MODX\Revolution\modResource::class, ['id' => $id, 'deleted' => 0]);
        if (!$resource) {
            return $this->notFound();
        }

        $data = [];
        foreach (self::STRING_FIELDS as $field) {
            $value = $resource->get($field);
            $data[$field] = $value === null ? null : (string) $value;
        }
        foreach (self::INT_FIELDS as $field) {
            $data[$field] = (int) $resource->get($field);
        }
        foreach (self::BOOL_FIELDS as $field) {
            $data[$field] = (bool) $resource->get($field);
        }
        $data['id'] = (int) $resource->get('id');
        $data['tvs'] = $this->tvValues($resource);

        return ['success' => true, 'data' => ['resource' => $data]];
    }

    /**
     * Read-only projection of the template variables bound to the resource template.
     *
     * Scalar values are normalized to strings (or null); structured values (for
     * example MIGX/JSON TV types) are JSON-encoded so the projection stays a flat
     * string map. Keys are the TV names, matching the `tv:<name>` write contract.
     */
    private function tvValues(\xPDOObject $resource): array
    {
        $values = [];
        $tvs = $resource->getMany('TemplateVars');
        if (!is_array($tvs)) {
            return $values;
        }
        foreach ($tvs as $tv) {
            if (!method_exists($tv, 'get') || !method_exists($tv, 'getValue')) {
                continue;
            }
            $name = (string) $tv->get('name');
            if ($name === '') {
                continue;
            }
            $values[$name] = $this->normalizeTvValue($tv->getValue($resource->get('id')));
        }
        return $values;
    }

    private function normalizeTvValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded === false ? null : $encoded;
    }

    private function notFound(): array
    {
        return ['success' => false, 'error' => ['code' => 'resource_not_found', 'message' => 'Resource not found.']];
    }
}
