<?php
declare(strict_types=1);
namespace AIBridge\Processors\Manager;
use AIBridge\Manager\AdminProcessor;
use AIBridge\Workflow\ApprovalService;
use AIBridge\Workflow\ChangeExecutionService;
use AIBridge\Workflow\ChangeRequestService;
final class WorkflowProcessor extends AdminProcessor {
 public function process(){ $denied=$this->requirePermission();if($denied)return $denied;$mode=(string)$this->getProperty('mode','list');$principal=['id'=>(string)$this->modx->user->get('id'),'type'=>'manager','manager_authorized'=>true,'profile_id'=>(int)$this->getProperty('profile_id',0)];try{ $changes=new ChangeRequestService($this->modx); $approvals=new ApprovalService($this->modx); return match($mode){
 'list'=>$this->success('', ['changes'=>$this->listChanges(),'approvals'=>$this->listApprovals()]),
 'create'=>$this->success('', $changes->create((string)$this->getProperty('operation'),json_decode((string)$this->getProperty('input','{}'),true)?:[],$principal,['request_id'=>bin2hex(random_bytes(16))],json_decode((string)$this->getProperty('qa','{}'),true)?:[])),
 'submit'=>$this->success('', $changes->submit((int)$this->getProperty('change_id'),$principal)),
 'request_approval'=>$this->success('', $approvals->create((int)$this->getProperty('change_id'),$principal)),
 'approve_decision'=>$this->success('', $approvals->decide((int)$this->getProperty('approval_id'),'approved',$principal,(string)$this->getProperty('comment',''))),
 'reject_decision'=>$this->success('', $approvals->decide((int)$this->getProperty('approval_id'),'rejected',$principal,(string)$this->getProperty('comment',''))),
 'approve'=>$this->success('', $changes->approve((int)$this->getProperty('change_id'),(int)$this->getProperty('approval_id'),$principal)),
 'reject'=>$this->success('', $changes->reject((int)$this->getProperty('change_id'),$principal,(string)$this->getProperty('reason',''))),
 'execute'=>$this->success('', (new ChangeExecutionService($this->modx))->dispatchApproved((int)$this->getProperty('change_id'),$principal)),
 default=>$this->failure('Unknown workflow mode.')}; }catch(\Throwable $e){return $this->failure($e->getMessage(),['code'=>'workflow_error']);}}
 private function listChanges():array{$out=[];$rows=$this->modx->getCollection('AIBridge\Model\ChangeRequest',[],['limit'=>200,'sortby'=>'created_at','sortdir'=>'DESC']);foreach($rows as $r)$out[]=$r->toArray();return $out;}
 private function listApprovals():array{$out=[];$rows=$this->modx->getCollection('AIBridge\Model\Approval',[],['limit'=>200,'sortby'=>'requested_at','sortdir'=>'DESC']);foreach($rows as $r)$out[]=$r->toArray();return $out;}
}
return WorkflowProcessor::class;
