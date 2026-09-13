<?php

declare(strict_types=1);

namespace AIBridge\Manager;

use AIBridge\Services\ContentContractService;
use AIBridge\Services\ContentQAService;
use AIBridge\Services\SiteIntelligenceService;
use MODX\Revolution\modX;

final class ResourceExplorerService
{
    public function __construct(private readonly modX $modx) {}

    /**
     * Nested resource tree below `$parent`. `$depth` is the number of nested
     * `children` levels: 0 is a flat list, 1 adds one child level, and so on;
     * `$limit` bounds each level and deeper levels are capped at 100 items.
     * Soft-deleted resources are never walked.
     */
    public function tree(int $parent = 0, int $limit = 100, int $depth = 2): array
    {
        $limit = max(1, min(500, $limit));
        $query = $this->modx->newQuery(\MODX\Revolution\modResource::class);
        $query->where(['parent' => max(0, $parent), 'deleted' => 0]);
        $query->limit($limit)->sortby('modResource.menuindex', 'ASC');
        $resources = $this->modx->getCollection(\MODX\Revolution\modResource::class, $query);
        $items = [];
        foreach ($resources ?: [] as $resource) {
            $id = (int) $resource->get('id');
            $items[] = $this->summary($resource, true);
            $items[array_key_last($items)]['has_children'] = (bool) $this->modx->getCount(\MODX\Revolution\modResource::class, ['parent' => $id, 'deleted' => 0]);
            if ($depth > 0 && $items[array_key_last($items)]['has_children']) {
                $items[array_key_last($items)]['children'] = $this->tree($id, 100, $depth - 1);
            }
        }
        return $items;
    }

    public function search(string $query = '', ?int $templateId = null, string $tvName = '', string $tvValue = '', int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $resourceQuery = $this->modx->newQuery(\MODX\Revolution\modResource::class);
        $resourceQuery->where(['deleted' => 0]);

        $query = trim($query);
        if ($query !== '') {
            $needle = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query) . '%';
            $resourceQuery->where([
                ['pagetitle:LIKE' => $needle],
                ['alias:LIKE' => $needle],
                ['description:LIKE' => $needle],
            ], \xPDO\Om\xPDOQuery::SQL_OR);
        }
        if ($templateId !== null && $templateId > 0) {
            $resourceQuery->where(['template' => $templateId]);
        }

        if ($tvName !== '') {
            $tv = $this->modx->getObject(\MODX\Revolution\modTemplateVar::class, ['name' => $tvName]);
            if (!$tv) return [];
            $resourceQuery->innerJoin(\MODX\Revolution\modTemplateVarResource::class, 'aibridge_tv', 'modResource.id = aibridge_tv.contentid');
            $resourceQuery->where(['aibridge_tv.tmplvarid' => (int) $tv->get('id')]);
            if ($tvValue !== '') {
                $resourceQuery->where(['aibridge_tv.value:LIKE' => '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $tvValue) . '%']);
            }
        }

        $resourceQuery->limit($limit)->sortby('modResource.editedon', 'DESC');
        $resources = $this->modx->getCollection(\MODX\Revolution\modResource::class, $resourceQuery);
        $items = [];
        foreach ($resources ?: [] as $resource) $items[] = $this->summary($resource, false);
        return $items;
    }

    public function get(int $id): ?array
    {
        $resource = $this->modx->getObject(\MODX\Revolution\modResource::class, ['id' => $id, 'deleted' => 0]);
        if (!$resource) return null;
        $data = $this->summary($resource, true);
        $data['content'] = (string) $resource->get('content');
        $data['description'] = (string) $resource->get('description');
        $data['introtext'] = (string) $resource->get('introtext');
        $data['longtitle'] = (string) $resource->get('longtitle');
        $data['published'] = (bool) $resource->get('published');
        $data['hidemenu'] = (bool) $resource->get('hidemenu');
        $data['menuindex'] = (int) $resource->get('menuindex');
        $data['parent'] = (int) $resource->get('parent');
        $data['alias'] = (string) $resource->get('alias');
        $data['class_key'] = (string) $resource->get('class_key');
        $data['context_key'] = (string) $resource->get('context_key');
        $data['tvs'] = $this->tvValues($resource);
        return $data;
    }

    public function contract(int $id): ?array
    {
        $resource = $this->modx->getObject(\MODX\Revolution\modResource::class, ['id' => $id, 'deleted' => 0]);
        if (!$resource) return null;
        $schema = (new SiteIntelligenceService($this->modx))->discover(['limit' => 500]);
        return (new ContentContractService())->resource($schema, (int) $resource->get('template'))->toArray();
    }

    public function qa(int $id, array $candidate = []): ?array
    {
        $resource = $this->get($id);
        if (!$resource) return null;
        $contract = $this->contract($id);
        if (!$contract) return null;
        $content = array_merge($resource, $candidate);
        return (new ContentQAService())->validate($content, $contract);
    }

    public function fingerprintDiff(int $fromId, int $toId): array
    {
        $from = $this->modx->getObject('AIBridge\\Model\\Fingerprint', $fromId);
        $to = $this->modx->getObject('AIBridge\\Model\\Fingerprint', $toId);
        if (!$from || !$to) return ['valid' => false, 'error' => 'fingerprint_not_found'];
        $a = json_decode((string) $from->get('snapshot_json'), true);
        $b = json_decode((string) $to->get('snapshot_json'), true);
        if (!is_array($a) || !is_array($b)) return ['valid' => false, 'error' => 'invalid_fingerprint_snapshot'];
        return ['valid' => true, 'from_id' => $fromId, 'to_id' => $toId, 'changes' => $this->diff($a, $b)];
    }

    private function summary(object $resource, bool $withState): array
    {
        $data = [
            'id' => (int) $resource->get('id'),
            'parent' => (int) $resource->get('parent'),
            'pagetitle' => (string) $resource->get('pagetitle'),
            'alias' => (string) $resource->get('alias'),
            'template' => (int) $resource->get('template'),
            'published' => (bool) $resource->get('published'),
            'hidemenu' => (bool) $resource->get('hidemenu'),
            'menuindex' => (int) $resource->get('menuindex'),
            'editedon' => (string) $resource->get('editedon'),
        ];
        if ($withState) {
            $data['deleted'] = (bool) $resource->get('deleted');
            $data['createdon'] = (string) $resource->get('createdon');
            $data['publishedon'] = (string) $resource->get('publishedon');
        }
        return $data;
    }

    private function tvValues(object $resource): array
    {
        $values = [];
        $tvs = $resource->getMany('TemplateVars');
        foreach ($tvs ?: [] as $tv) {
            $values[(string) $tv->get('name')] = (string) $tv->getValue($resource->get('id'));
        }
        return $values;
    }

    private function diff(array $from, array $to, string $path = ''): array
    {
        $changes = [];
        foreach (array_unique(array_merge(array_keys($from), array_keys($to))) as $key) {
            $p = $path === '' ? (string) $key : $path . '.' . $key;
            if (!array_key_exists($key, $from)) { $changes[] = ['path' => $p, 'type' => 'added', 'to' => $to[$key]]; continue; }
            if (!array_key_exists($key, $to)) { $changes[] = ['path' => $p, 'type' => 'removed', 'from' => $from[$key]]; continue; }
            if (is_array($from[$key]) && is_array($to[$key])) { $changes = array_merge($changes, $this->diff($from[$key], $to[$key], $p)); continue; }
            if ($from[$key] !== $to[$key]) $changes[] = ['path' => $p, 'type' => 'changed', 'from' => $from[$key], 'to' => $to[$key]];
        }
        return $changes;
    }
}
