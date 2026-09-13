<?php

declare(strict_types=1);

namespace AIBridge\Api;

use AIBridge\Application\Application;
use AIBridge\Configuration\ConfigFactory;
use AIBridge\MCP\McpServer;
use AIBridge\Model\Profile;
use AIBridge\Queue\Job;
use AIBridge\Queue\QueueManager;
use AIBridge\Security\Authorization;
use AIBridge\Security\IpAllowlist;
use AIBridge\Security\RateLimiter;
use AIBridge\Security\SecurityDecisionPipeline;
use AIBridge\Security\TokenAuthenticator;
use AIBridge\Services\PolicyService;
use MODX\Revolution\modX;

/**
 * Transport-agnostic REST front controller for /api/ai/v2/*.
 *
 * Security order: authentication -> rate limit -> IP/authorization/approval
 * pipeline -> idempotency header contract -> Application services.
 * Business logic lives in Application services; this class only routes and
 * enforces the external boundary.
 */
final class RestApi
{
    private string $requestId = '';

    public function __construct(private readonly modX $modx) {}

    /**
     * @return array{status:int,body:array<string,mixed>,headers:array<string,string>}
     */
    public function handle(string $method, string $path, array $headers = [], array $body = [], string $clientIp = '', array $query = []): array
    {
        $method = strtoupper($method);
        $path = '/' . trim($path, '/');
        $headers = array_change_key_case($headers, CASE_LOWER);
        $incoming = trim((string) ($headers['x-request-id'] ?? ''));
        $requestId = preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $incoming) === 1 ? $incoming : bin2hex(random_bytes(16));
        $this->requestId = $requestId;

        if ($path === '/health') {
            return $this->json(200, [
                'status' => 'ok',
                'component' => 'modx-ai-bridge',
                'version' => (string) $this->modx->getOption('aibridge_version', null, '0.1.3'),
                'request_id' => $requestId,
            ]);
        }

        if ($path === '/ready') {
            $readiness = (new \AIBridge\Manager\OperationsConsoleService($this->modx))->readiness();
            return $this->json($readiness['status'] === 'ready' ? 200 : 503, [
                'component' => 'modx-ai-bridge',
                'status' => $readiness['status'],
                'version' => (string) $this->modx->getOption('aibridge_version', null, '0.1.3'),
                'checks' => $readiness['checks'],
                'request_id' => $requestId,
            ]);
        }

        $config = ConfigFactory::fromModx($this->modx);
        if (!$config->get('rest_enabled', false)) {
            return $this->error(503, 'rest_disabled', 'REST API is disabled.', [], $requestId);
        }

        $auth = (new TokenAuthenticator($this->modx))->authenticate((string) ($headers['authorization'] ?? ''));
        if (!($auth['authenticated'] ?? false)) {
            return $this->error(401, 'authentication_failed', 'Authentication failed.', ['reason' => (string) ($auth['reason'] ?? 'unknown')], $requestId);
        }
        $principal = $auth['principal'];

        $rate = (new RateLimiter($this->modx))->consume(
            'token:' . $principal['id'],
            (int) $config->get('rate_limit_per_minute', 60),
            60,
            (int) ($principal['profile_id'] ?? 0)
        );
        if (!($rate['allowed'] ?? false)) {
            return $this->error(429, 'rate_limited', 'Rate limit exceeded.', ['retry_after' => (int) ($rate['retry_after'] ?? 60)], $requestId, ['Retry-After' => (string) (int) ($rate['retry_after'] ?? 60)]);
        }

        if ($path === '/mcp') {
            return $this->handleMcp($method, $body, $principal, $clientIp, $config->get('mcp_enabled', false), $requestId);
        }

        $application = new Application($this->modx);

        return match (true) {
            $method === 'GET' && $path === '/capabilities' => $this->read($principal, 'site.read', $clientIp, $requestId, fn () => $application->aiCapabilities()),
            $method === 'GET' && $path === '/profiles' => $this->profiles($principal, $clientIp, $requestId),
            $method === 'GET' && $path === '/site/schema' => $this->read($principal, 'site.schema', $clientIp, $requestId, fn () => $application->discoverSite($query)),
            $method === 'GET' && $path === '/site/fingerprint' => $this->read($principal, 'site.fingerprint', $clientIp, $requestId, fn () => ['fingerprint' => $application->discoverSite()['fingerprint'] ?? null]),
            $method === 'GET' && $path === '/content/contract' => $this->read($principal, 'site.read', $clientIp, $requestId, fn () => $application->buildContentContract($application->discoverSite(), isset($query['template_id']) ? (int) $query['template_id'] : null)),
            $method === 'POST' && $path === '/content/validate' => $this->read($principal, 'content.validate', $clientIp, $requestId, fn () => $application->validateContent((array) ($body['content'] ?? []), (array) ($body['contract'] ?? []))),
            $method === 'POST' && $path === '/resources/preview' => $this->preview($principal, $body, $clientIp, $requestId),
            $method === 'POST' && $path === '/resources' => $this->mutate('resource.create', $headers, $body, $principal, $clientIp, $requestId),
            $method === 'PATCH' && preg_match('#^/resources/(\d+)$#', $path, $m) === 1 => $this->mutate('resource.update', $headers, array_merge($body, ['id' => (int) $m[1]]), $principal, $clientIp, $requestId),
            $method === 'DELETE' && preg_match('#^/resources/(\d+)$#', $path, $m) === 1 => $this->mutate('resource.delete', $headers, array_merge($body, ['id' => (int) $m[1]]), $principal, $clientIp, $requestId),
            $method === 'POST' && preg_match('#^/resources/(\d+)/publish$#', $path, $m) === 1 => $this->mutate('resource.publish', $headers, array_merge($body, ['id' => (int) $m[1]]), $principal, $clientIp, $requestId),
            $method === 'GET' && preg_match('#^/resources/(\d+)$#', $path, $m) === 1 => $this->resourceRead((int) $m[1], $principal, $clientIp, $requestId),
            $method === 'GET' && preg_match('#^/jobs/(\d+)$#', $path, $m) === 1 => $this->job((int) $m[1], $principal, $clientIp, $requestId),
            default => $this->error(404, 'not_found', 'Endpoint not found.', ['path' => $path], $requestId),
        };
    }

    private function handleMcp(string $method, array $body, array $principal, string $clientIp, mixed $mcpEnabled, string $requestId): array
    {
        if ($method !== 'POST') {
            return $this->error(405, 'method_not_allowed', 'MCP requires POST.', [], $requestId);
        }
        if (!$mcpEnabled) {
            return $this->error(503, 'mcp_disabled', 'MCP endpoint is disabled.', [], $requestId);
        }
        if (!is_array($body) || ($body['jsonrpc'] ?? null) !== '2.0') {
            return $this->json(200, ['jsonrpc' => '2.0', 'id' => $body['id'] ?? null, 'error' => ['code' => -32600, 'message' => 'Invalid Request']]);
        }
        $params = is_array($body['params'] ?? null) ? $body['params'] : [];
        $params['_client_ip'] = $clientIp;
        $body['params'] = $params;
        $response = McpServer::fromModx($this->modx)->handle($body, $principal + ['client_ip' => $clientIp]);
        return $this->json(200, $response);
    }

    private function read(array $principal, string $operation, string $clientIp, string $requestId, callable $handler): array
    {
        $decision = $this->decide($principal, $operation, $clientIp, $requestId);
        if (!$decision->allowed()) {
            return $this->denied($decision->toArray(), $requestId);
        }
        try {
            return $this->json(200, ['success' => true, 'data' => $handler(), 'request_id' => $requestId]);
        } catch (\Throwable $e) {
            return $this->error(500, 'internal_error', 'Operation failed.', [], $requestId);
        }
    }

    private function profiles(array $principal, string $clientIp, string $requestId): array
    {
        $decision = $this->decide($principal, 'site.read', $clientIp, $requestId);
        if (!$decision->allowed()) {
            return $this->denied($decision->toArray(), $requestId);
        }
        $criteria = [];
        $profileId = (int) ($principal['profile_id'] ?? 0);
        if ($profileId > 0) {
            $criteria['id'] = $profileId;
        }
        $profiles = $this->modx->getCollection(Profile::class, $criteria, ['limit' => 100, 'sortby' => 'id', 'sortdir' => 'ASC']);
        $items = [];
        foreach ($profiles ?: [] as $profile) {
            $items[] = [
                'id' => (int) $profile->get('id'),
                'name' => (string) $profile->get('name'),
                'site_key' => (string) $profile->get('site_key'),
                'status' => (string) $profile->get('status'),
                'environment' => (string) $profile->get('environment'),
            ];
        }
        return $this->json(200, ['success' => true, 'data' => ['profiles' => $items], 'request_id' => $requestId]);
    }

    private function preview(array $principal, array $body, string $clientIp, string $requestId): array
    {
        $decision = $this->decide($principal, 'resource.preview', $clientIp, $requestId);
        if (!$decision->allowed()) {
            return $this->denied($decision->toArray(), $requestId);
        }
        $result = (new Application($this->modx))->resourcePreview($body, $principal, ['ip' => $clientIp, 'request_id' => $requestId]);
        return $this->applicationResult($result, $requestId);
    }

    private function mutate(string $operation, array $headers, array $input, array $principal, string $clientIp, string $requestId): array
    {
        $idempotencyKey = trim((string) ($headers['idempotency-key'] ?? ''));
        if ($idempotencyKey === '') {
            return $this->error(400, 'idempotency_key_required', 'Idempotency-Key header is required for mutating operations.', ['operation' => $operation], $requestId);
        }

        $decision = $this->decide($principal, $operation, $clientIp, $requestId);
        if (!$decision->allowed()) {
            return $this->denied($decision->toArray(), $requestId);
        }

        $request = [
            'ip' => $clientIp,
            'request_id' => $requestId,
            'idempotency_key' => $idempotencyKey,
            'approval_id' => (string) ($input['approval_id'] ?? ''),
            'change_id' => (int) ($input['change_id'] ?? 0),
        ];

        $job = new Job(
            'resource_execution',
            ['operation' => $operation, 'input' => $input, 'principal' => $principal, 'request' => $request],
            3,
            300,
            $idempotencyKey,
            (string) $principal['id'],
            $requestId,
            null,
            (int) ($principal['profile_id'] ?? 0)
        );

        try {
            $jobId = (int) (new Application($this->modx))->dispatchJob($job);
        } catch (\Throwable $e) {
            return $this->error(500, 'job_dispatch_failed', 'Unable to enqueue the operation.', [], $requestId);
        }

        return $this->json(202, [
            'success' => true,
            'job_id' => $jobId,
            'status' => 'queued',
            'operation' => $operation,
            'request_id' => $requestId,
        ]);
    }

    private function resourceRead(int $id, array $principal, string $clientIp, string $requestId): array
    {
        $decision = $this->decide($principal, 'resource.read', $clientIp, $requestId);
        if (!$decision->allowed()) {
            return $this->denied($decision->toArray(), $requestId);
        }
        return $this->applicationResult((new Application($this->modx))->resourceRead($id), $requestId);
    }

    private function job(int $id, array $principal, string $clientIp, string $requestId): array
    {
        $decision = $this->decide($principal, 'site.read', $clientIp, $requestId);
        if (!$decision->allowed()) {
            return $this->denied($decision->toArray(), $requestId);
        }
        $result = (new Application($this->modx))->jobStatus($id);
        $profileId = (int) ($principal['profile_id'] ?? 0);
        if (($result['success'] ?? false) && $profileId > 0 && (int) ($result['job']['profile_id'] ?? 0) !== $profileId) {
            return $this->error(404, 'job_not_found', 'Job not found.', [], $requestId);
        }
        return $this->applicationResult($result, $requestId);
    }

    private function applicationResult(array $result, string $requestId): array
    {
        if (($result['success'] ?? false) === true) {
            return $this->json(200, $result + ['request_id' => $requestId]);
        }
        $code = (string) ($result['error']['code'] ?? 'operation_failed');
        $status = match ($code) {
            'job_not_found', 'resource_not_found' => 404,
            'approval_required', 'approval_invalid', 'ip_denied', 'policy_denied', 'operation_not_allowed' => 403,
            default => 400,
        };
        return $this->error($status, $code, (string) ($result['error']['message'] ?? 'Operation failed.'), $result['data'] ?? [], $requestId);
    }

    private function decide(array $principal, string $operation, string $clientIp, string $requestId): \AIBridge\Security\SecurityDecision
    {
        $config = ConfigFactory::fromModx($this->modx);
        $pipeline = new SecurityDecisionPipeline($config, new Authorization(), new IpAllowlist(), new PolicyService($config), $this->modx);
        return $pipeline->decide(['ip' => $clientIp, 'channel' => 'rest', 'request_id' => $requestId], $principal, $operation);
    }

    private function denied(array $decision, string $requestId): array
    {
        return $this->error(403, (string) ($decision['code'] ?? 'policy_denied'), 'Operation denied by security policy.', [], $requestId);
    }

    private function json(int $status, array $body, array $headers = []): array
    {
        return ['status' => $status, 'body' => $body, 'headers' => ['Content-Type' => 'application/json; charset=utf-8', 'X-Request-Id' => $this->requestId] + $headers];
    }

    private function error(int $status, string $code, string $message, array $details, string $requestId, array $headers = []): array
    {
        return $this->json($status, [
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
                'request_id' => $requestId,
            ],
        ], $headers);
    }
}
