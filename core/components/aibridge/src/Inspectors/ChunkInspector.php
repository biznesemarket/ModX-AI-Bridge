<?php

declare(strict_types=1);

namespace AIBridge\Inspectors;

use MODX\Revolution\modX;

final class ChunkInspector
{
    public function __construct(private readonly modX $modx)
    {
    }

    public function inspect(): array
    {
        $rows = [];
        foreach ($this->modx->getCollection(\MODX\Revolution\modChunk::class, [], ['sortby' => 'name', 'sortdir' => 'ASC']) as $chunk) {
            $rows[] = [
                'id' => (int) $chunk->get('id'),
                'name' => (string) $chunk->get('name'),
                'description' => (string) $chunk->get('description'),
                'category_id' => (int) $chunk->get('category'),
                'locked' => (bool) $chunk->get('locked'),
            ];
        }
        return $rows;
    }
}
