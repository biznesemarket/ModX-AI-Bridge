<?php

declare(strict_types=1);

namespace AIBridge\Services;

use MODX\Revolution\modX;

final class PreviewService
{
    public function __construct(private readonly modX $modx) {}

    /** Safe content-level preview. It does not execute snippets or save the resource. */
    public function preview(array $input = []): array
    {
        $id = (int) ($input['id'] ?? 0);
        $resource = $id > 0 ? $this->modx->getObject(\MODX\Revolution\modResource::class, $id) : null;
        if (!$resource) throw new \RuntimeException('Resource not found.');

        $content = $input['content'] ?? (string) $resource->get('content');
        $content = is_string($content) ? $content : '';
        $url = $this->modx->makeUrl($id, '', [], 'full');

        return [
            'resource_id' => $id,
            'mode' => 'content',
            'url' => $url,
            'pagetitle' => (string) $resource->get('pagetitle'),
            'content' => $content,
            'executed_snippets' => false,
            'persisted' => false,
        ];
    }
}
