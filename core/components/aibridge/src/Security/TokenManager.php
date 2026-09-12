<?php

declare(strict_types=1);
namespace AIBridge\Security;
use AIBridge\Configuration\BridgeConfig;
use MODX\Revolution\modX;
final class TokenManager
{
    public function __construct(private readonly modX $modx, private readonly BridgeConfig $config) {}
    public function issue(string $name, array $scopes, int $profileId, ?int $ttlDays = null): array
    {
        if ($profileId < 1) throw new \InvalidArgumentException('profile_id is required for token issuance.');
        $plain=bin2hex(random_bytes(32)); $hash=hash('sha256',$plain); $days=$ttlDays??(int)$this->config->get('token_expiry_days',90);
        $row=$this->modx->newObject(\AIBridge\Model\Token::class); $row->fromArray(['profile_id'=>$profileId,'name'=>$name,'token_hash'=>$hash,'scopes_json'=>json_encode(array_values(array_unique(array_map('strval',$scopes)))),'status'=>'active','expires_at'=>date('Y-m-d H:i:s',time()+$days*86400),'created_at'=>date('Y-m-d H:i:s')]);
        if(!$row->save()) throw new \RuntimeException('Token persistence failed');
        return ['id'=>(string)$row->get('id'),'name'=>$name,'token'=>$plain,'expires_at'=>$row->get('expires_at'),'scopes'=>$scopes];
    }
    public function revoke(int $id): bool { $row=$this->modx->getObject(\AIBridge\Model\Token::class,$id); if(!$row)return false; $row->set('status','revoked'); return (bool)$row->save(); }
}
