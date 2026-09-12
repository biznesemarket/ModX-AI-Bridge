<?php

declare(strict_types=1);

namespace AIBridge\Inspectors;

use MODX\Revolution\modX;

final class SnippetInspector
{
    public function __construct(private readonly modX $modx)
    {
    }

    public function inspect(): array
    {
        $rows = [];
        foreach ($this->modx->getCollection(\MODX\Revolution\modSnippet::class, [], ['sortby' => 'name', 'sortdir' => 'ASC']) as $snippet) {
            $rows[] = [
                'id' => (int) $snippet->get('id'),
                'name' => (string) $snippet->get('name'),
                'description' => (string) $snippet->get('description'),
                'category_id' => (int) $snippet->get('category'),
                'locked' => (bool) $snippet->get('locked'),
            ];
        }
        return $rows;
    }
}
