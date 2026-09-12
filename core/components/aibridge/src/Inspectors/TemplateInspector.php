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
        $rows = [];
        foreach ($this->modx->getCollection(\MODX\Revolution\modTemplate::class, [], ['sortby' => 'templatename', 'sortdir' => 'ASC']) as $template) {
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
