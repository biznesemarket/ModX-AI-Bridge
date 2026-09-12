<?php

declare(strict_types=1);
namespace AIBridge\Security;
final class SecretRedactor
{
    private array $keys=['token','access_token','authorization','password','secret','api_key','token_hash','client_secret'];
    public function redact(array $data): array { foreach($data as $k=>$v) if(in_array(strtolower((string)$k),$this->keys,true)) $data[$k]='[REDACTED]'; return $data; }
    public function redactRecursive(array $data): array { foreach($data as $k=>$v) $data[$k]=is_array($v)?$this->redactRecursive($v):(in_array(strtolower((string)$k),$this->keys,true)?'[REDACTED]':$v); return $data; }
    public function redactText(string $text): string
    {
        foreach ($this->keys as $key) {
            $text = preg_replace('/(' . preg_quote($key, '/') . '\s*[=:]\s*)(?:Bearer\s+)?[^\s,;"\']+/i', '$1[REDACTED]', $text) ?? $text;
        }
        $text = preg_replace('/Bearer\s+[A-Za-z0-9._\-]+/i', 'Bearer [REDACTED]', $text) ?? $text;
        return $text;
    }
}
