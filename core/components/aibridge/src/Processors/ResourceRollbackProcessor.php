<?php
declare(strict_types=1);
namespace AIBridge\Processors;
use AIBridge\Verification\RollbackService;
use MODX\Revolution\Processors\Processor;
final class ResourceRollbackProcessor extends Processor {
 public function process(){
  $snapshotId=(int)$this->getProperty('snapshot_id'); if($snapshotId<1)return $this->failure('snapshot_id is required.');
  $principal=['type'=>'manager','id'=>(string)($this->modx->user?->get('id')??'manager'),'profile_id'=>(int)$this->getProperty('profile_id')];
  $result=(new RollbackService($this->modx))->rollback($snapshotId,$principal,['request_id'=>$this->getProperty('request_id')?:bin2hex(random_bytes(8)),'ip'=>'manager']);
  return ($result['success']??false)?$this->success('',$result):$this->failure((string)($result['message']??'Rollback denied.'),$result);
 }
}
return ResourceRollbackProcessor::class;
