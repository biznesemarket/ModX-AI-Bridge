<?php

declare(strict_types=1);

namespace AIBridge\Services;

use MODX\Revolution\modX;

/**
 * Read-back access to persisted MODX resources.
 *
 * `read()` returns a stable, whitelisted projection of one resource, including
 * the template variables bound to its template under the `tvs` map. `list()`
 * returns a filtered, paginated collection using a lighter summary projection
 * (no content/TVs) so a listing stays bounded. Both surfaces are authorized as
 * read operations by the REST and MCP fronts.
 */
final class ResourceReadService
{
    public const LIST_LIMIT_DEFAULT = 25;
    public const LIST_LIMIT_MAX = 100;

    private const STRING_FIELDS = [
        'pagetitle', 'longtitle', 'description', 'introtext', 'content', 'alias',
        'class_key', 'context_key', 'publishedon', 'createdon', 'editedon',
    ];
    private const INT_FIELDS = ['id', 'parent', 'template', 'menuindex'];
    private const BOOL_FIELDS = ['published', 'hidemenu', 'deleted', 'searchable'];

    private const LIST_STRING_FIELDS = [
        'pagetitle', 'alias', 'class_key', 'context_key', 'createdon', 'editedon', 'publishedon',
    ];
    private const LIST_INT_FIELDS = ['id', 'parent', 'template', 'menuindex'];
    private const LIST_BOOL_FIELDS = ['published', 'hidemenu', 'searchable'];
    private const SORTABLE = ['id', 'pagetitle', 'alias', 'menuindex', 'editedon', 'createdon', 'publishedon'];

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

        return ['success' => true, 'data' => ['resource' => $this->project($resource)]];
    }

    /**
     * Filtered, paginated read-back of non-deleted resources.
     *
     * Supported filters: `parent`, `template`, `context_key`, `published`,
     * `tv_name`/`tv_value` (match an explicit template-variable value),
     * `q`/`search` (pagetitle/alias/description LIKE), `limit` (1..100, default
     * 25), `offset`, `sort` (whitelisted column), `dir` (`asc`/`desc`). Invalid
     * or out-of-range values return `invalid_filter`; unknown keys are ignored.
     */
    public function list(array $query): array
    {
        $limit = self::LIST_LIMIT_DEFAULT;
        if (isset($query['limit']) && $query['limit'] !== '') {
            if (!is_numeric($query['limit']) || (int) $query['limit'] < 1) {
                return $this->invalidFilter('limit');
            }
            $limit = min(self::LIST_LIMIT_MAX, (int) $query['limit']);
        }

        $offset = 0;
        if (isset($query['offset']) && $query['offset'] !== '') {
            if (!is_numeric($query['offset']) || (int) $query['offset'] < 0) {
                return $this->invalidFilter('offset');
            }
            $offset = (int) $query['offset'];
        }

        $and = ['deleted' => 0];

        if (isset($query['parent']) && $query['parent'] !== '') {
            if (!is_numeric($query['parent']) || (int) $query['parent'] < 0) {
                return $this->invalidFilter('parent');
            }
            $and['parent'] = (int) $query['parent'];
        }

        if (isset($query['template']) && $query['template'] !== '') {
            if (!is_numeric($query['template']) || (int) $query['template'] < 1) {
                return $this->invalidFilter('template');
            }
            $and['template'] = (int) $query['template'];
        }

        if (isset($query['context_key']) && trim((string) $query['context_key']) !== '') {
            $contextKey = trim((string) $query['context_key']);
            if (strlen($contextKey) > 64) {
                return $this->invalidFilter('context_key');
            }
            $and['context_key'] = $contextKey;
        }

        if (isset($query['published']) && $query['published'] !== '') {
            $published = $this->parseBool($query['published']);
            if ($published === null) {
                return $this->invalidFilter('published');
            }
            $and['published'] = $published ? 1 : 0;
        }

        $tvId = null;
        $tvValueNeedle = null;
        $tvNameParam = isset($query['tv_name']) ? trim((string) $query['tv_name']) : '';
        $tvValueParam = isset($query['tv_value']) ? trim((string) $query['tv_value']) : '';
        if ($tvValueParam !== '' && $tvNameParam === '') {
            return $this->invalidFilter('tv_value', 'tv_value requires tv_name.');
        }
        if ($tvNameParam !== '') {
            if (strlen($tvNameParam) > 191) {
                return $this->invalidFilter('tv_name');
            }
            $tv = $this->modx->getObject(\MODX\Revolution\modTemplateVar::class, ['name' => $tvNameParam]);
            if (!$tv) {
                return $this->invalidFilter('tv_name', 'Unknown template variable: ' . $tvNameParam . '.');
            }
            $tvId = (int) $tv->get('id');
            if ($tvValueParam !== '') {
                if (strlen($tvValueParam) > 191) {
                    return $this->invalidFilter('tv_value');
                }
                $tvValueNeedle = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $tvValueParam) . '%';
            }
        }

        $searchNeedle = null;
        $search = '';
        foreach (['q', 'search'] as $key) {
            if (isset($query[$key]) && trim((string) $query[$key]) !== '') {
                $search = trim((string) $query[$key]);
                break;
            }
        }
        if ($search !== '') {
            if (strlen($search) > 191) {
                return $this->invalidFilter('q');
            }
            $searchNeedle = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
        }

        $sort = 'id';
        if (isset($query['sort']) && $query['sort'] !== '') {
            $sort = (string) $query['sort'];
            if (!in_array($sort, self::SORTABLE, true)) {
                return $this->invalidFilter('sort');
            }
        }

        $dir = 'ASC';
        if (isset($query['dir']) && $query['dir'] !== '') {
            $dir = strtoupper((string) $query['dir']);
            if (!in_array($dir, ['ASC', 'DESC'], true)) {
                return $this->invalidFilter('dir');
            }
        }

        $total = (int) $this->modx->getCount(\MODX\Revolution\modResource::class, $this->listQuery($and, $searchNeedle, $tvId, $tvValueNeedle));
        $page = $this->listQuery($and, $searchNeedle, $tvId, $tvValueNeedle);
        $page->limit($limit, $offset);
        $page->sortby('modResource.' . $sort, $dir);
        $collection = $this->modx->getCollection(\MODX\Revolution\modResource::class, $page);

        $items = [];
        foreach ($collection ?: [] as $resource) {
            $items[] = $this->listItem($resource);
        }

        return ['success' => true, 'data' => [
            'resources' => $items,
            'count' => count($items),
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]];
    }

    /**
     * Build the list filter query. The LIKE search is a single OR group so it is
     * combined with the AND filters (`deleted`, `parent`, ...), unlike the flat
     * `OR:` criteria keys which would OR the whole clause. A TV filter is applied
     * as a join on `modTemplateVarResource` (one row per resource/TV), so no ids
     * are materialized and `count`/`total` stay a single aggregate query.
     */
    private function listQuery(array $and, ?string $searchNeedle, ?int $tvId = null, ?string $tvValueNeedle = null): \xPDO\Om\xPDOQuery
    {
        $query = $this->modx->newQuery(\MODX\Revolution\modResource::class);
        if ($tvId !== null) {
            $query->innerJoin(\MODX\Revolution\modTemplateVarResource::class, 'aibridge_tv', 'modResource.id = aibridge_tv.contentid');
            $query->where(['aibridge_tv.tmplvarid' => $tvId]);
            if ($tvValueNeedle !== null) {
                $query->where(['aibridge_tv.value:LIKE' => $tvValueNeedle]);
            }
        }
        $query->where($and);
        if ($searchNeedle !== null) {
            $query->where([
                ['pagetitle:LIKE' => $searchNeedle],
                ['alias:LIKE' => $searchNeedle],
                ['description:LIKE' => $searchNeedle],
            ], \xPDO\Om\xPDOQuery::SQL_OR);
        }
        return $query;
    }

    private function project(\xPDOObject $resource): array
    {
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

        return $data;
    }

    private function listItem(\xPDOObject $resource): array
    {
        $data = [];
        foreach (self::LIST_STRING_FIELDS as $field) {
            $value = $resource->get($field);
            $data[$field] = $value === null ? null : (string) $value;
        }
        foreach (self::LIST_INT_FIELDS as $field) {
            $data[$field] = (int) $resource->get($field);
        }
        foreach (self::LIST_BOOL_FIELDS as $field) {
            $data[$field] = (bool) $resource->get($field);
        }

        return $data;
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

    private function parseBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['1', 'true', 'yes'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no'], true)) {
            return false;
        }
        return null;
    }

    private function invalidFilter(string $name, ?string $message = null): array
    {
        return ['success' => false, 'error' => ['code' => 'invalid_filter', 'message' => $message ?? ('Invalid filter: ' . $name . '.')]];
    }

    private function notFound(): array
    {
        return ['success' => false, 'error' => ['code' => 'resource_not_found', 'message' => 'Resource not found.']];
    }
}
