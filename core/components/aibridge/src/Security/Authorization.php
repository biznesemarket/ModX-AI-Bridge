<?php

declare(strict_types=1);

namespace AIBridge\Security;

final class Authorization
{
    public const SCOPE_READ = 'site:read';
    public const SCOPE_VALIDATE = 'content:validate';
    public const SCOPE_PREVIEW = 'resource:preview';
    public const SCOPE_READ_RESOURCE = 'resource:read';
    public const SCOPE_WRITE = 'resource:write';
    public const SCOPE_DELETE = 'resource:delete';
    public const SCOPE_PUBLISH = 'resource:publish';
    public const SCOPE_ROLLBACK = 'resource:rollback';
    public const SCOPE_SETTINGS = 'settings:write';

    public function allows(array $principal, string $operation, array $resource = []): bool
    {
        if (($principal['type'] ?? '') === 'manager' && ($principal['manager_authorized'] ?? false) === true) {
            return in_array($operation, [
                'resource.preview','resource.read','resource.create','resource.update','resource.delete','resource.publish','resource.rollback',
                'site.schema','site.fingerprint','site.read','content.validate'
            ], true);
        }
        $scopes = array_map('strval', $principal['scopes'] ?? []);
        $required = match ($operation) {
            'site.schema','site.fingerprint','site.read' => [self::SCOPE_READ],
            'content.validate' => [self::SCOPE_VALIDATE],
            'resource.preview' => [self::SCOPE_PREVIEW],
            'resource.read' => [self::SCOPE_READ_RESOURCE],
            'resource.create','resource.update' => [self::SCOPE_WRITE],
            'resource.delete' => [self::SCOPE_DELETE],
            'resource.publish' => [self::SCOPE_PUBLISH],
            'resource.rollback' => [self::SCOPE_ROLLBACK],
            'settings.write' => [self::SCOPE_SETTINGS],
            default => [],
        };
        if (!$required) return false;
        return count(array_intersect($required, $scopes)) === count($required)
            || in_array('*', $scopes, true);
    }
}
