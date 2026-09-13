<?php
declare(strict_types=1);
namespace AIBridge\Processors;
use AIBridge\Manager\AdminProcessor;
use AIBridge\Verification\RollbackService;
final class ResourceRollbackProcessor extends AdminProcessor {
 public function process(){
  if($error=$this->requirePermission())return $error;
  $snapshotId=(int)$this->getProperty('snapshot_id'); if($snapshotId<1)return $this->failure('snapshot_id is required.', ['code'=>'invalid_snapshot']);
  $principal=['type'=>'manager','id'=>(string)($this->modx->user?->get('id')??'manager'),'scopes'=>['*'],'manager_authorized'=>true,'profile_id'=>(int)$this->getProperty('profile_id')];
  try {
   $result=(new RollbackService($this->modx))->rollback($snapshotId,$principal,['request_id'=>$this->getProperty('request_id')?:bin2hex(random_bytes(8)),'ip'=>'manager','channel'=>'manager']);
  } catch(\Throwable $e) {
   return $this->failure('Rollback failed.', ['code'=>'rollback_failed']);
  }
  return ($result['success']??false)?$this->success('',$result):$this->failure((string)($result['message']??'Rollback denied.'),$result);
 }
}
return ResourceRollbackProcessor::class;
