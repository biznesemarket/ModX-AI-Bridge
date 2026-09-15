<?php

declare(strict_types=1);

namespace AIBridge\Manager;

use MODX\Revolution\modX;

final class OperationsConsoleService
{
    public function __construct(private readonly modX $modx) {}

    public function overview(): array
    {
        return [
            'component' => 'modx-ai-bridge',
            'version' => (string) $this->modx->getOption('aibridge_version', null, '0.11.0'),
            'php' => PHP_VERSION,
            'modx' => defined('MODX_VERSION') ? MODX_VERSION : 'unknown',
            'environment' => (string) $this->modx->getOption('aibridge_environment', null, 'production'),
            'timestamp' => gmdate('c'),
            'readiness' => $this->readiness(),
        ];
    }

    public function readiness(): array
    {
        $checks = [
            'modx' => isset($this->modx) && $this->modx instanceof modX,
            'database' => $this->databaseReady(),
            'service_container' => (bool) $this->modx->services,
            'component_namespace' => (bool) $this->modx->getObject(\MODX\Revolution\modNamespace::class, ['name' => 'aibridge']),
        ];

        return [
            'status' => !in_array(false, $checks, true) ? 'ready' : 'not_ready',
            'checks' => $checks,
        ];
    }

    public function profiles(int $limit = 100): array
    {
        $objects = $this->modx->getCollection('AIBridge\\Model\\Profile', $this->query('AIBridge\\Model\\Profile', $limit));
        $items = [];
        foreach ($objects ?: [] as $profile) {
            $items[] = [
                'id' => (int) $profile->get('id'),
                'name' => (string) $profile->get('name'),
                'site_key' => (string) $profile->get('site_key'),
                'status' => (string) $profile->get('status'),
                'environment' => (string) $profile->get('environment'),
                'base_url' => (string) $profile->get('base_url'),
                'created_at' => (string) $profile->get('created_at'),
                'updated_at' => (string) $profile->get('updated_at'),
            ];
        }
        return $items;
    }

    public function tokens(int $limit = 100): array
    {
        $objects = $this->modx->getCollection('AIBridge\\Model\\Token', $this->query('AIBridge\\Model\\Token', $limit));
        $items = [];
        foreach ($objects ?: [] as $token) {
            $scopes = json_decode((string) $token->get('scopes_json'), true);
            $items[] = [
                'id' => (int) $token->get('id'),
                'profile_id' => (int) $token->get('profile_id'),
                'name' => (string) $token->get('name'),
                'status' => (string) $token->get('status'),
                'expires_at' => $token->get('expires_at'),
                'last_used_at' => $token->get('last_used_at'),
                'scopes' => is_array($scopes) ? array_values($scopes) : [],
                'created_at' => (string) $token->get('created_at'),
                // token_hash is deliberately never returned.
            ];
        }
        return $items;
    }

    public function policies(int $limit = 100): array
    {
        $objects = $this->modx->getCollection('AIBridge\\Model\\Policy', $this->query('AIBridge\\Model\\Policy', $limit));
        $items = [];
        foreach ($objects ?: [] as $policy) {
            $rules = json_decode((string) $policy->get('rules_json'), true);
            $items[] = [
                'id' => (int) $policy->get('id'),
                'profile_id' => (int) $policy->get('profile_id'),
                'name' => (string) $policy->get('name'),
                'status' => (string) $policy->get('status'),
                'rules' => is_array($rules) ? $rules : [],
                'created_at' => (string) $policy->get('created_at'),
            ];
        }
        return $items;
    }

    public function jobs(int $limit = 100): array
    {
        $objects = $this->modx->getCollection('AIBridge\\Model\\Job', $this->query('AIBridge\\Model\\Job', $limit, 'created_at', 'DESC'));
        $items = [];
        foreach ($objects ?: [] as $job) {
            $items[] = [
                'id' => (int) $job->get('id'),
                'profile_id' => (int) $job->get('profile_id'),
                'type' => (string) $job->get('type'),
                'status' => (string) $job->get('status'),
                'progress' => (int) $job->get('progress'),
                'attempts' => (int) $job->get('attempts'),
                'max_attempts' => (int) $job->get('max_attempts'),
                'available_at' => (string) $job->get('available_at'),
                'locked_at' => (string) $job->get('locked_at'),
                'locked_by' => (string) $job->get('locked_by'),
                'created_at' => (string) $job->get('created_at'),
                'finished_at' => (string) $job->get('finished_at'),
            ];
        }
        return $items;
    }

    public function audit(int $limit = 100): array
    {
        $objects = $this->modx->getCollection('AIBridge\\Model\\AuditEvent', $this->query('AIBridge\\Model\\AuditEvent', $limit, 'created_at', 'DESC'));
        $items = [];
        foreach ($objects ?: [] as $event) {
            $context = json_decode((string) $event->get('context_json'), true);
            $items[] = [
                'id' => (int) $event->get('id'),
                'profile_id' => (int) $event->get('profile_id'),
                'event' => (string) $event->get('event'),
                'actor_type' => (string) $event->get('actor_type'),
                'actor_id' => (string) $event->get('actor_id'),
                'operation' => (string) $event->get('operation'),
                'resource_id' => $event->get('resource_id') === null ? null : (int) $event->get('resource_id'),
                'request_id' => (string) $event->get('request_id'),
                'context' => is_array($context) ? $context : [],
                'created_at' => (string) $event->get('created_at'),
            ];
        }
        return $items;
    }

    public function fingerprints(int $limit = 100): array
    {
        $objects = $this->modx->getCollection('AIBridge\\Model\\Fingerprint', $this->query('AIBridge\\Model\\Fingerprint', $limit, 'created_at', 'DESC'));
        $items = [];
        foreach ($objects ?: [] as $fingerprint) {
            $items[] = [
                'id' => (int) $fingerprint->get('id'),
                'profile_id' => (int) $fingerprint->get('profile_id'),
                'algorithm' => (string) $fingerprint->get('algorithm'),
                'fingerprint' => (string) $fingerprint->get('fingerprint'),
                'created_at' => (string) $fingerprint->get('created_at'),
            ];
        }
        return $items;
    }

    public function changes(int $limit = 100): array
    {
        $objects = $this->modx->getCollection('AIBridge\\Model\\ChangeRequest', $this->query('AIBridge\\Model\\ChangeRequest', $limit, 'created_at', 'DESC'));
        $items = [];
        foreach ($objects ?: [] as $change) {
            $items[] = [
                'id' => (int) $change->get('id'),
                'profile_id' => (int) $change->get('profile_id'),
                'operation' => (string) $change->get('operation'),
                'resource_id' => $change->get('resource_id') === null ? null : (int) $change->get('resource_id'),
                'status' => (string) $change->get('status'),
                'input' => $this->decodeJson((string) $change->get('input_json')),
                'before' => $this->decodeJson((string) $change->get('before_json')),
                'after' => $this->decodeJson((string) $change->get('after_json')),
                'diff' => $this->decodeJson((string) $change->get('diff_json')),
                'qa' => $this->decodeJson((string) $change->get('qa_json')),
                'approval_id' => $change->get('approval_id') === null ? null : (int) $change->get('approval_id'),
                'job_id' => $change->get('job_id') === null ? null : (int) $change->get('job_id'),
                'requested_by' => (string) $change->get('requested_by'),
                'request_id' => (string) $change->get('request_id'),
                'rejection_reason' => (string) $change->get('rejection_reason'),
                'created_at' => (string) $change->get('created_at'),
                'updated_at' => (string) $change->get('updated_at'),
            ];
        }
        return $items;
    }

    public function approvals(int $limit = 100): array
    {
        $objects = $this->modx->getCollection('AIBridge\\Model\\Approval', $this->query('AIBridge\\Model\\Approval', $limit, 'requested_at', 'DESC'));
        $items = [];
        foreach ($objects ?: [] as $approval) {
            $items[] = [
                'id' => (int) $approval->get('id'),
                'change_id' => (int) $approval->get('change_id'),
                'status' => (string) $approval->get('status'),
                'requested_by' => (string) $approval->get('requested_by'),
                'requested_at' => (string) $approval->get('requested_at'),
                'decided_by' => (string) $approval->get('decided_by'),
                'decided_at' => (string) $approval->get('decided_at'),
                'comment' => (string) $approval->get('comment'),
            ];
        }
        return $items;
    }

    public function setProfileStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['active', 'disabled', 'retired'], true)) return false;
        $profile = $this->modx->getObject('AIBridge\\Model\\Profile', $id);
        if (!$profile) return false;
        $profile->set('status', $status);
        $profile->set('updated_at', gmdate('Y-m-d H:i:s'));
        return (bool) $profile->save();
    }

    public function setTokenStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['active', 'disabled', 'revoked'], true)) return false;
        $token = $this->modx->getObject('AIBridge\\Model\\Token', $id);
        if (!$token) return false;
        $token->set('status', $status);
        return (bool) $token->save();
    }

    public function setPolicyStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['active', 'disabled'], true)) return false;
        $policy = $this->modx->getObject('AIBridge\\Model\\Policy', $id);
        if (!$policy) return false;
        $policy->set('status', $status);
        return (bool) $policy->save();
    }

    private function query(string $class, int $limit, ?string $sortBy = null, string $sortDir = 'ASC'): \xPDO\Om\xPDOQuery
    {
        $query = $this->modx->newQuery($class);
        $query->limit(max(1, min(500, $limit)));
        if ($sortBy !== null) {
            $query->sortby($sortBy, $sortDir);
        }
        return $query;
    }

    private function decodeJson(string $json): array
    {
        if ($json === '') return [];
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function databaseReady(): bool
    {
        try {
            return (bool) $this->modx->query('SELECT 1');
        } catch (\Throwable) {
            return false;
        }
    }
}
