<?php

declare(strict_types=1);

namespace AIBridge\Inspectors;

use MODX\Revolution\modX;

final class TemplateInspector
{
    public function __construct(private readonly modX $modx)
    {
    }

    public function inspect(): array
    {
        $query = $this->modx->newQuery(\MODX\Revolution\modTemplate::class);
        $query->sortby('templatename', 'ASC');
        $rows = [];
        foreach ($this->modx->getCollection(\MODX\Revolution\modTemplate::class, $query) as $template) {
            $rows[] = [
                'id' => (int) $template->get('id'),
                'name' => (string) $template->get('templatename'),
                'description' => (string) $template->get('description'),
                'category_id' => (int) $template->get('category'),
                'locked' => (bool) $template->get('locked'),
                'content_type' => (string) $template->get('contentType'),
                'mime_type' => (string) $template->get('mime_type'),
            ];
        }
        return $rows;
    }
}
