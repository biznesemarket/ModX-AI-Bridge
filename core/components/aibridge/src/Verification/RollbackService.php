<?php
declare(strict_types=1);
namespace AIBridge\Verification;
use AIBridge\Audit\AuditService;
use AIBridge\Security\SecurityDecisionPipeline;
use AIBridge\Configuration\ConfigFactory;
use AIBridge\Security\Authorization;
use AIBridge\Security\IpAllowlist;
use AIBridge\Services\PolicyService;
use MODX\Revolution\modX;
final class RollbackService {
 public function __construct(private readonly modX $modx) {}
 public function rollback(int $snapshotId,array $principal,array $request=[]):array {
  $snapshot=$this->modx->getObject(\AIBridge\Model\Snapshot::class,$snapshotId);if(!$snapshot)throw new \RuntimeException('Snapshot not found.');
  $resourceId=(int)$snapshot->get('resource_id');$data=json_decode((string)$snapshot->get('data_json'),true);if(!is_array($data))throw new \RuntimeException('Snapshot data is invalid.');
  $config=ConfigFactory::fromModx($this->modx);$pipeline=new SecurityDecisionPipeline($config,new Authorization(),new IpAllowlist(),new PolicyService($config),$this->modx);
  $decision=$pipeline->decide($request+['request_id'=>$request['request_id']??bin2hex(random_bytes(8))],$principal,'resource.rollback');if(!$decision->allowed())return ['success'=>false,'code'=>$decision->code(),'message'=>'Rollback denied by security policy.'];
  $principalProfile=(int)($principal['profile_id']??0);if($principalProfile>0&&(int)$snapshot->get('profile_id')!==$principalProfile)return ['success'=>false,'code'=>'profile_mismatch','message'=>'Snapshot belongs to another profile.'];
  $resource=$this->modx->getObject(\MODX\Revolution\modResource::class,['id'=>$resourceId]);if(!$resource)throw new \RuntimeException('Target resource not found; automatic recreation from snapshot is disabled.');
  $allowed=['pagetitle','longtitle','description','introtext','content','alias','parent','template','menuindex','published','hidemenu','class_key','context_key'];$payload=[];foreach($allowed as $k)if(array_key_exists($k,$data))$payload[$k]=$data[$k];
  $connection=$this->modx->getConnection();$pdo=is_object($connection)?($connection->pdo??null):null;if(!$pdo instanceof \PDO)throw new \RuntimeException('MODX database connection unavailable.');
  try{$pdo->beginTransaction();$resource->fromArray($payload);if(!$resource->save())throw new \RuntimeException('Rollback resource save failed.');$this->applyTvs($resource,$data['__aibridge']['tvs']??[]);if(!$resource->save())throw new \RuntimeException('Rollback TV save failed.');if(!$pdo->commit())throw new \RuntimeException('Rollback commit failed.');}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
  (new AuditService($this->modx))->record('resource_rollback',['profile_id'=>(int)($principal['profile_id']??0),'snapshot_id'=>$snapshotId,'resource_id'=>$resourceId,'actor_type'=>$principal['type']??'unknown','actor_id'=>$principal['id']??'','request_id'=>$request['request_id']??'']);return ['success'=>true,'snapshot_id'=>$snapshotId,'resource_id'=>$resourceId];
 }
 private function applyTvs(\xPDOObject $resource,mixed $tvs):void{if(!is_array($tvs))return;foreach($tvs as $name=>$value){$tv=$this->modx->getObject(\MODX\Revolution\modTemplateVar::class,['name'=>(string)$name]);if($tv)$resource->setTVValue((int)$tv->get('id'),is_scalar($value)?(string)$value:json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}}
}
