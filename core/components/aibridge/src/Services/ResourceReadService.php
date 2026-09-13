<?php

declare(strict_types=1);

namespace AIBridge\Services;

use MODX\Revolution\modX;

/**
 * Read-back access to a persisted MODX resource.
 *
 * Returns a stable, whitelisted projection so AI clients can verify the result
 * of a queued mutation without direct database access. Template variables are
 * intentionally not included yet (additive follow-up).
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

        return ['success' => true, 'data' => ['resource' => $data]];
    }

    private function notFound(): array
    {
        return ['success' => false, 'error' => ['code' => 'resource_not_found', 'message' => 'Resource not found.']];
    }
}
