<?php

declare(strict_types=1);

namespace AIBridge\Execution;

use AIBridge\Configuration\ConfigFactory;
use AIBridge\Security\Authorization;
use AIBridge\Security\IdempotencyService;
use AIBridge\Security\IpAllowlist;
use AIBridge\Security\SecretRedactor;
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
    /** Matches the `idempotency_key` column width used by every backing table. */
    public const MAX_IDEMPOTENCY_KEY_LENGTH = 190;

    private SecurityDecisionPipeline $security;
    private IdempotencyService $idempotency;
    private ContentContractService $contracts;
    private ContentQAService $qa;
    private SnapshotService $snapshots;
    private CacheInvalidationService $cache;
    private PreviewService $preview;
    private AuditService $audit;
    private SecretRedactor $redactor;

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
        $this->redactor = new SecretRedactor();
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
        catch (\Throwable $e) {
            // Raw exception text can carry SQL, paths or payload fragments; keep
            // the client-facing message stable and log the redacted diagnostic.
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, 'AIBridge resource.preview failed: ' . $this->redactor->redactText($e->getMessage()));
            return (new ExecutionResult(false, 'resource.preview', [], [], 'preview_failed', 'Resource preview failed.'))->toArray();
        }
    }

    private function mutate(string $operation, array $input, array $principal, array $request): array
    {
        $requestId = (string) ($request['request_id'] ?? bin2hex(random_bytes(16)));
        $decision = $this->security->decide($request + ['request_id' => $requestId, 'resource_id' => (int) ($input['id'] ?? 0)], $principal, $operation);
        if (!$decision->allowed()) return (new ExecutionResult(false, $operation, [], [], $decision->code(), 'Security policy denied.'))->toArray();

        $key = trim((string) ($request['idempotency_key'] ?? $input['idempotency_key'] ?? ''));
        if ($key === '') return (new ExecutionResult(false, $operation, [], [], 'idempotency_key_required', 'Idempotency-Key is required for mutating operations.'))->toArray();
        if (mb_strlen($key, 'UTF-8') > self::MAX_IDEMPOTENCY_KEY_LENGTH) {
            return (new ExecutionResult(false, $operation, [], [], 'idempotency_key_invalid', 'Idempotency-Key must be at most ' . self::MAX_IDEMPOTENCY_KEY_LENGTH . ' characters.'))->toArray();
        }
        $principalId = (string) ($principal['id'] ?? 'anonymous');
        $profileId = (int) ($principal['profile_id'] ?? 0);
        $hash = hash('sha256', json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $idem = $this->idempotency->begin($key, $principalId, $operation, $hash, $profileId);
        if (!($idem['accepted'] ?? false)) {
            if (($idem['replay'] ?? false) && is_array($idem['response'] ?? null)) return $idem['response'];
            return (new ExecutionResult(false, $operation, [], [], ($idem['conflict'] ?? false) ? 'idempotency_conflict' : 'idempotency_unavailable', 'Idempotency request cannot be accepted.'))->toArray();
        }

        $connection = $this->modx->getConnection();
        $pdo = is_object($connection) ? ($connection->pdo ?? null) : null;
        if (!$pdo instanceof \PDO) throw new \RuntimeException('MODX database connection unavailable.');
        $snapshot = null; $resource = null; $warnings = [];
        try {
            if ($operation === 'resource.create') {
                $templateId = (int) ($input['template'] ?? 0);
                if ($templateId < 1) throw new \InvalidArgumentException('template is required for create.');
                $resource = $this->modx->newObject(\MODX\Revolution\modResource::class);
                $payload = $this->sanitizePayload($input);
                $payload['template'] = $templateId;
                $resource->fromArray($payload);
            } else {
                $id = (int) ($input['id'] ?? 0);
                if ($id < 1) throw new \InvalidArgumentException('id is required.');
                $resource = $this->modx->getObject(\MODX\Revolution\modResource::class, $id);
                if (!$resource) throw new \RuntimeException('Resource not found.');
            }

            $current = $resource->toArray();
            $candidate = $operation === 'resource.create' ? $this->sanitizePayload($input) : array_merge($current, $this->sanitizePayload($input));
            if ($operation === 'resource.publish') $candidate['published'] = 1;
            if ($operation !== 'resource.delete') {
                $schema = (new SiteIntelligenceService($this->modx))->discover(['limit' => 100]);
                $contract = $this->contracts->resource($schema, (int) $resource->get('template'))->toArray();
                $qa = $this->qa->validate($candidate, $contract);
                if (!$qa['valid']) return $this->finish($operation, new ExecutionResult(false, $operation, ['qa' => $qa], [], 'content_qa_failed', 'Content QA failed.'), $key, $principalId, $profileId);
                $warnings = $qa['warnings'];
            }

            $deadlineAt = (float) ($request['_deadline_at'] ?? 0.0);
            if ($deadlineAt > 0.0 && microtime(true) >= $deadlineAt) {
                // No side effect has run yet, so release the key instead of
                // completing it: a transient timeout must remain retryable
                // rather than replay the failure forever (manager changes use a
                // deterministic key). The queue handler turns this result into a
                // terminal JobTimeoutException.
                $this->idempotency->abandon($key, $principalId, $operation, $profileId);
                return (new ExecutionResult(false, $operation, [], [], 'execution_timeout', 'Job deadline exceeded before execution.'))->toArray();
            }

            if ($operation === 'resource.delete') {
                $snapshot = $this->snapshots->createForResource($resource, $operation, $profileId);
            } elseif ($operation !== 'resource.create') {
                $snapshot = $this->snapshots->createForResource($resource, $operation, $profileId);
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
            if (!$pdo->commit()) throw new \RuntimeException('Transaction commit failed.');
            // Invalidate after the commit so the (context-scoped) cache work does
            // not extend the write transaction's lock hold time.
            $cache = $this->cache->invalidateResource($resourceId, $resource);

            $data = ['resource_id' => $resourceId, 'snapshot' => $snapshot, 'cache' => $cache, 'qa' => ['valid' => true, 'warnings' => $warnings], 'request_id' => $requestId];
            $result = new ExecutionResult(true, $operation, $data, $warnings);
            $this->audit->record('resource_execution', ['profile_id' => $profileId, 'actor_type' => $principal['type'] ?? 'token', 'actor_id' => $principalId, 'operation' => $operation, 'resource_id' => $resourceId, 'request_id' => $requestId, 'result' => $data]);
            return $this->finish($operation, $result, $key, $principalId, $profileId);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $diagnostic = $this->redactor->redactText($e->getMessage());
            $this->audit->record('resource_execution_failed', ['profile_id' => $profileId, 'actor_type' => $principal['type'] ?? 'token', 'actor_id' => $principalId, 'operation' => $operation, 'request_id' => $requestId, 'error' => $diagnostic]);
            return $this->finish($operation, new ExecutionResult(false, $operation, ['snapshot' => $snapshot], $warnings, 'execution_failed', 'Resource execution failed.'), $key, $principalId, $profileId);
        }
    }

    private function finish(string $operation, ExecutionResult $result, string $key, string $principalId, int $profileId): array
    {
        $data = $result->toArray();
        $this->idempotency->complete($key, $principalId, $operation, $data, $profileId);
        return $data;
    }

    private function sanitizePayload(array $input): array
    {
        // `published` is intentionally not writable here: publishing is a
        // separate, approval-gated operation (`resource.publish`). Allowing it
        // through create/update would bypass that gate for any principal with
        // resource:write. Publish state is set explicitly by the publish branch.
        $allowed = ['pagetitle','longtitle','description','introtext','content','alias','parent','template','menuindex','hidemenu','class_key','context_key'];
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
            $tv = $this->modx->getObject(\MODX\Revolution\modTemplateVar::class, ['name' => $tvName]);
            if ($tv) $resource->setTVValue((int) $tv->get('id'), is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }
}
