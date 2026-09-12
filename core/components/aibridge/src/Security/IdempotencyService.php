<?php

declare(strict_types=1);

namespace AIBridge\Security;

use MODX\Revolution\modX;

final class IdempotencyService
{
    public function __construct(private readonly modX $modx) {}
    public function begin(string $key, string $principal, string $operation, string $requestHash): array
    {
        $where=['idempotency_key'=>$key,'principal_id'=>$principal,'operation'=>$operation];
        $row=$this->modx->getObject(\AIBridge\Model\IdempotencyKey::class,$where);
        if ($row) {
            if ((string)$row->get('request_hash') !== $requestHash) return ['accepted'=>false,'conflict'=>true,'reason'=>'idempotency_key_reused'];
            return ['accepted'=>false,'replay'=>true,'response'=>json_decode((string)$row->get('response_json'),true) ?: null];
        }
        $row=$this->modx->newObject(\AIBridge\Model\IdempotencyKey::class);
        $row->fromArray($where + ['request_hash'=>$requestHash,'status'=>'in_progress','created_at'=>date('Y-m-d H:i:s')]);
        if (!$row->save()) return ['accepted'=>false,'reason'=>'idempotency_persistence_failed'];
        return ['accepted'=>true,'replay'=>false];
    }
    public function complete(string $key,string $principal,string $operation,array $response): void
    {
        $row=$this->modx->getObject(\AIBridge\Model\IdempotencyKey::class,['idempotency_key'=>$key,'principal_id'=>$principal,'operation'=>$operation]);
        if (!$row) return;
        $row->set('status','completed'); $row->set('response_json',json_encode($response,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); $row->save();
    }
}
