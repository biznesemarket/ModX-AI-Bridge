<?php

declare(strict_types=1);

namespace AIBridge\Execution;

use AIBridge\Configuration\ConfigFactory;
use AIBridge\Security\Authorization;
use AIBridge\Security\IdempotencyService;
use AIBridge\Security\IpAllowlist;
use AIBridge\Security\SecurityDecisionPipeline;
use AIBridge\Services\CacheInvalidationService;
use AIBridge\Services\ContentContractService;
use AIBridge\Services\ContentQAService;
use AIBridge\Services\PolicyService;
use AIBridge\Services\PreviewService;
use AIBridge\Services\SiteIntelligenceService;
use AIBridge\Services\SnapshotService;
use AIBridge\Audit\AuditService;
use MODX\Revolution\modX;

final class ResourceExecutionService
{
    private SecurityDecisionPipeline $security;
    private IdempotencyService $idempotency;
    private ContentContractService $contracts;
    private ContentQAService $qa;
    private SnapshotService $snapshots;
    private CacheInvalidationService $cache;
    private PreviewService $preview;
    private AuditService $audit;

    public function __construct(private readonly modX $modx)
    {
        $config = ConfigFactory::fromModx($modx);
        $this->security = new SecurityDecisionPipeline($config, new Authorization(), new IpAllowlist(), new PolicyService($config), $modx);
        $this->idempotency = new IdempotencyService($modx);
        $this->contracts = new ContentContractService();
        $this->qa = new ContentQAService();
        $this->snapshots = new SnapshotService($modx);
        $this->cache = new CacheInvalidationService($modx);
        $this->preview = new PreviewService($modx);
        $this->audit = new AuditService($modx);
    }

    public function create(array $input, array $principal, array $request = []): array { return $this->mutate('resource.create', $input, $principal, $request); }
    public function update(array $input, array $principal, array $request = []): array { return $this->mutate('resource.update', $input, $principal, $request); }
    public function delete(array $input, array $principal, array $request = []): array { return $this->mutate('resource.delete', $input, $principal, $request); }
    public function publish(array $input, array $principal, array $request = []): array { return $this->mutate('resource.publish', $input, $principal, $request); }

    public function preview(array $input, array $principal, array $request = []): array
    {
        $decision = $this->security->decide($request + ['ip' => $request['ip'] ?? ''], $principal, 'resource.preview');
        if (!$decision->allowed()) return (new ExecutionResult(false, 'resource.preview', [], [], $decision->code(), 'Security policy denied.'))->toArray();
        try { return (new ExecutionResult(true, 'resource.preview', $this->preview->preview($input)))->toArray(); }
        catch (\Throwable $e) { return (new ExecutionResult(false, 'resource.preview', [], [], 'preview_failed', $e->getMessage()))->toArray(); }
    }

    private function mutate(string $operation, array $input, array $principal, array $request): array
    {
        $requestId = (string) ($request['request_id'] ?? bin2hex(random_bytes(16)));
        $decision = $this->security->decide($request + ['request_id' => $requestId], $principal, $operation);
        if (!$decision->allowed()) return (new ExecutionResult(false, $operation, [], [], $decision->code(), 'Security policy denied.'))->toArray();

        $key = trim((string) ($request['idempotency_key'] ?? $input['idempotency_key'] ?? ''));
        if ($key === '') return (new ExecutionResult(false, $operation, [], [], 'idempotency_key_required', 'Idempotency-Key is required for mutating operations.'))->toArray();
        $principalId = (string) ($principal['id'] ?? 'anonymous');
        $hash = hash('sha256', json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $idem = $this->idempotency->begin($key, $principalId, $operation, $hash);
        if (!($idem['accepted'] ?? false)) {
            if (($idem['replay'] ?? false) && is_array($idem['response'] ?? null)) return $idem['response'];
            return (new ExecutionResult(false, $operation, [], [], ($idem['conflict'] ?? false) ? 'idempotency_conflict' : 'idempotency_unavailable', 'Idempotency request cannot be accepted.'))->toArray();
        }

        $pdo = $this->modx->getConnection();
        if (!$pdo) throw new \RuntimeException('MODX database connection unavailable.');
        $snapshot = null; $resource = null; $warnings = [];
        try {
            if ($operation === 'resource.create') {
                $templateId = (int) ($input['template'] ?? 0);
                if ($templateId < 1) throw new \InvalidArgumentException('template is required for create.');
                $resource = $this->modx->newObject('modResource');
                $payload = $this->sanitizePayload($input);
                $payload['template'] = $templateId;
                $resource->fromArray($payload);
            } else {
                $id = (int) ($input['id'] ?? 0);
                if ($id < 1) throw new \InvalidArgumentException('id is required.');
                $resource = $this->modx->getObject('modResource', $id);
                if (!$resource) throw new \RuntimeException('Resource not found.');
            }

            $current = $resource->toArray();
            $candidate = $operation === 'resource.create' ? $this->sanitizePayload($input) : array_merge($current, $this->sanitizePayload($input));
            if ($operation === 'resource.publish') $candidate['published'] = 1;
            if ($operation !== 'resource.delete') {
                $schema = (new SiteIntelligenceService($this->modx))->discover(['limit' => 100]);
                $contract = $this->contracts->resource($schema, (int) $resource->get('template'))->toArray();
                $qa = $this->qa->validate($candidate, $contract);
                if (!$qa['valid']) return $this->finish($operation, new ExecutionResult(false, $operation, ['qa' => $qa], [], 'content_qa_failed', 'Content QA failed.'), $key, $principalId);
                $warnings = $qa['warnings'];
            }

            if ($operation === 'resource.delete') {
                $snapshot = $this->snapshots->createForResource($resource, $operation);
            } elseif ($operation !== 'resource.create') {
                $snapshot = $this->snapshots->createForResource($resource, $operation);
            }

            $pdo->beginTransaction();
            if ($operation === 'resource.create' || $operation === 'resource.update' || $operation === 'resource.publish') {
                if ($operation !== 'resource.create') $resource->fromArray($this->sanitizePayload($input));
                if ($operation === 'resource.publish') {
                    $resource->set('published', 1);
                    $resource->set('publishedon', date('Y-m-d H:i:s'));
                }
                if (!$resource->save()) throw new \RuntimeException('Resource save failed.');
                $this->applyTvValues($resource, $input['tvs'] ?? []);
                $resource->save();
            } elseif ($operation === 'resource.delete') {
                if (!$resource->remove()) throw new \RuntimeException('Resource delete failed.');
            }
            $resourceId = (int) $resource->get('id');
            $cache = $this->cache->invalidateResource($resourceId);
            if (!$pdo->commit()) throw new \RuntimeException('Transaction commit failed.');

            $data = ['resource_id' => $resourceId, 'snapshot' => $snapshot, 'cache' => $cache, 'qa' => ['valid' => true, 'warnings' => $warnings], 'request_id' => $requestId];
            $result = new ExecutionResult(true, $operation, $data, $warnings);
            $this->audit->record('resource_execution', ['actor_type' => $principal['type'] ?? 'token', 'actor_id' => $principalId, 'operation' => $operation, 'resource_id' => $resourceId, 'request_id' => $requestId, 'result' => $data]);
            return $this->finish($operation, $result, $key, $principalId);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $this->audit->record('resource_execution_failed', ['actor_type' => $principal['type'] ?? 'token', 'actor_id' => $principalId, 'operation' => $operation, 'request_id' => $requestId, 'error' => $e->getMessage()]);
            return $this->finish($operation, new ExecutionResult(false, $operation, ['snapshot' => $snapshot], $warnings, 'execution_failed', $e->getMessage()), $key, $principalId);
        }
    }

    private function finish(string $operation, ExecutionResult $result, string $key, string $principalId): array
    {
        $data = $result->toArray();
        $this->idempotency->complete($key, $principalId, $operation, $data);
        return $data;
    }

    private function sanitizePayload(array $input): array
    {
        $allowed = ['pagetitle','longtitle','description','introtext','content','alias','parent','template','menuindex','published','hidemenu','class_key','context_key'];
        $payload = [];
        foreach ($allowed as $key) if (array_key_exists($key, $input)) $payload[$key] = $input[$key];
        return $payload;
    }

    private function applyTvValues(\xPDOObject $resource, mixed $tvs): void
    {
        if (!is_array($tvs)) return;
        foreach ($tvs as $name => $value) {
            $tvName = str_starts_with((string) $name, 'tv:') ? substr((string) $name, 3) : (string) $name;
            if ($tvName === '') continue;
            $tv = $this->modx->getObject('modTemplateVar', ['name' => $tvName]);
            if ($tv) $resource->setTVValue((int) $tv->get('id'), is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }
}
