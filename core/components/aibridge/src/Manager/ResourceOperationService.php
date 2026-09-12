<?php
declare(strict_types=1);
namespace AIBridge\Manager;
use AIBridge\Execution\ResourceExecutionService;
use AIBridge\Workflow\ApprovalService;
use AIBridge\Workflow\ChangeRequestService;
use AIBridge\Workflow\ChangeExecutionService;
use AIBridge\Workflow\ChangeState;
use MODX\Revolution\modX;
final class ResourceOperationService {
 public function __construct(private readonly modX $modx) {}
 public function dispatch(string $operation,array $input,array $manager):array {
  $allowed=['resource.create','resource.update','resource.delete','resource.preview','resource.publish'];if(!in_array($operation,$allowed,true))return ['success'=>false,'error'=>['code'=>'operation_not_allowed']];
  $principal=['id'=>(string)($manager['id']??$this->modx->user->get('id')),'type'=>'manager','scopes'=>['*'],'manager_authorized'=>true,'profile_id'=>(int)($manager['profile_id']??0)];$requestId=bin2hex(random_bytes(16));
  if($operation==='resource.preview')return $this->previewNow($input);
  $contract=(new ChangeRequestService($this->modx))->create($operation,$input,$principal,['request_id'=>$requestId,'profile_id'=>$principal['profile_id']],[]);$changeId=(int)$contract['id'];(new ChangeRequestService($this->modx))->submit($changeId,$principal);
  $dangerous=in_array($operation,['resource.delete','resource.publish'],true);
  if($dangerous){$approval=(new ApprovalService($this->modx))->create($changeId,$principal);return ['success'=>true,'status'=>ChangeState::PENDING_APPROVAL,'change_id'=>$changeId,'approval_id'=>(int)$approval['id'],'operation'=>$operation,'request_id'=>$requestId];}
  (new ChangeRequestService($this->modx))->autoApprove($changeId,['id'=>'system','type'=>'system']);$result=(new ChangeExecutionService($this->modx))->dispatchApproved($changeId,$principal);return ['success'=>true]+$result+['operation'=>$operation,'request_id'=>$requestId];
 }
 public function previewNow(array $input):array{$principal=['id'=>'manager','type'=>'manager','scopes'=>['*'],'manager_authorized'=>true];$request=['channel'=>'manager','ip'=>'manager','request_id'=>bin2hex(random_bytes(16))];return (new ResourceExecutionService($this->modx))->preview($input,$principal,$request);}
}
