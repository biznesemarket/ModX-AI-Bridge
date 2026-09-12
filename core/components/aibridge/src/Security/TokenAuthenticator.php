<?php

declare(strict_types=1);

namespace AIBridge\Security;

use MODX\Revolution\modX;

final class TokenAuthenticator
{
    public function __construct(private readonly modX $modx, private readonly SecretRedactor $redactor = new SecretRedactor()) {}

    public function authenticate(string $authorizationHeader): array
    {
        if (!preg_match('/^Bearer\s+(.+)$/i', trim($authorizationHeader), $m)) {
            return ['authenticated' => false, 'reason' => 'invalid_authorization_scheme'];
        }
        $plain = trim($m[1]);
        if ($plain === '' || strlen($plain) > 4096) return ['authenticated' => false, 'reason' => 'invalid_token'];
        $hash = hash('sha256', $plain);
        $token = $this->modx->getObject(\AIBridge\Model\Token::class, ['token_hash' => $hash]);
        if (!$token) return ['authenticated' => false, 'reason' => 'invalid_token'];
        if ((string)$token->get('status') !== 'active') return ['authenticated' => false, 'reason' => 'token_inactive'];
        $expires = $token->get('expires_at');
        if ($expires && strtotime((string)$expires) <= time()) return ['authenticated' => false, 'reason' => 'token_expired'];
        $profileId = (int) $token->get('profile_id');
        if ($profileId < 1) return ['authenticated' => false, 'reason' => 'token_profile_missing'];
        $profile = $this->modx->getObject(\AIBridge\Model\Profile::class, ['id' => $profileId, 'status' => 'active']);
        if (!$profile) return ['authenticated' => false, 'reason' => 'profile_inactive'];
        $scopes = json_decode((string)$token->get('scopes_json'), true);
        if (!is_array($scopes)) $scopes = [];
        $token->set('last_used_at', date('Y-m-d H:i:s'));
        $token->save();
        return [
            'authenticated' => true,
            'principal' => ['type'=>'token','id'=>(string)$token->get('id'),'name'=>(string)$token->get('name'),'scopes'=>$scopes,'profile_id'=>(int)$token->get('profile_id')],
        ];
    }
}
