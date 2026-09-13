<?php
declare(strict_types=1);
namespace AIBridge\Workflow;
use AIBridge\Audit\AuditService;
use AIBridge\Security\SecretRedactor;
use AIBridge\Verification\VerificationService;
use MODX\Revolution\modX;
final class ChangeRequestService {
 public function __construct(private readonly modX $modx, private readonly SecretRedactor $redactor=new SecretRedactor()) {}
 public function create(string $operation,array $input,array $principal,array $request,array $qa=[]):array {
  $profileId=(int)($principal['profile_id']??$request['profile_id']??0); if($profileId<1) throw new \InvalidArgumentException('profile_id is required for a change request.');
  if(isset($input['tvs'])&&is_array($input['tvs'])){$normalizedTvs=[];foreach($input['tvs'] as $tvKey=>$tvValue){$normalizedTvs[str_starts_with((string)$tvKey,'tv:')?substr((string)$tvKey,3):(string)$tvKey]=$tvValue;}$input['tvs']=$normalizedTvs;}
  // `published` is not writable through create/update (publish is a separate,
  // approval-gated operation); drop it here too so the recorded `after_json`
  // matches what execution actually applies and post-execution verification
  // does not fail on a field the executor intentionally ignores.
  if($operation!=='resource.publish')unset($input['published']);
  $id=isset($input['id'])?(int)$input['id']:null; $diff=(new ChangeDiffService($this->modx,$this->redactor))->build($id,$input); if($operation==='resource.publish'){$diff['after']['published']=1;$diff['after']['publishedon']=VerificationService::PRESENT;} $now=gmdate('Y-m-d H:i:s');
  $row=$this->modx->newObject(\AIBridge\Model\ChangeRequest::class);
  $row->fromArray(['profile_id'=>$profileId,'operation'=>$operation,'resource_id'=>$id,'status'=>ChangeState::DRAFT,'input_json'=>json_encode($this->redactor->redactRecursive($input),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),'before_json'=>json_encode($diff['before'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),'after_json'=>json_encode($diff['after'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),'diff_json'=>json_encode($diff['changes'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),'qa_json'=>json_encode($this->redactor->redactRecursive($qa),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),'requested_by'=>(string)($principal['id']??''),'request_id'=>(string)($request['request_id']??''),'created_at'=>$now,'updated_at'=>$now]);
  if(!$row->save())throw new \RuntimeException('Change request persistence failed.');
  (new AuditService($this->modx))->record('change_created',['profile_id'=>$profileId,'actor_type'=>$principal['type']??'unknown','actor_id'=>$principal['id']??'','operation'=>$operation,'resource_id'=>$id,'request_id'=>$request['request_id']??'','change_id'=>(int)$row->get('id')]);
  return $row->toArray();
 }
 public function submit(int $id,array $principal):array{return $this->transition($id,ChangeState::PENDING_APPROVAL,$principal,'change_submitted');}
 public function approve(int $id,int $approvalId,array $principal):array {
  $row=$this->require($id,$principal); if((string)$row->get('status')!==ChangeState::PENDING_APPROVAL)throw new \RuntimeException('Change is not pending approval.');
  if(!(new ApprovalService($this->modx))->isApproved($approvalId,$id))throw new \RuntimeException('Approval does not authorize this change.');
  $row->set('approval_id',$approvalId);$row->set('status',ChangeState::APPROVED);$row->set('updated_at',gmdate('Y-m-d H:i:s'));if(!$row->save())throw new \RuntimeException('Change approval update failed.');
  (new AuditService($this->modx))->record('change_approved',['profile_id'=>(int)$row->get('profile_id'),'change_id'=>$id,'approval_id'=>$approvalId,'actor_type'=>$principal['type']??'unknown','actor_id'=>$principal['id']??'']);return $row->toArray();
 }
 public function autoApprove(int $id,array $principal):array {
  $row=$this->require($id,$principal); if((string)$row->get('status')!==ChangeState::PENDING_APPROVAL)throw new \RuntimeException('Change is not pending approval.');
  $row->set('status',ChangeState::APPROVED);$row->set('updated_at',gmdate('Y-m-d H:i:s'));if(!$row->save())throw new \RuntimeException('Change auto-approval failed.');
  (new AuditService($this->modx))->record('change_auto_approved',['profile_id'=>(int)$row->get('profile_id'),'change_id'=>$id,'actor_type'=>$principal['type']??'system','actor_id'=>$principal['id']??'']);return $row->toArray();
 }
 public function reject(int $id,array $principal,string $reason=''):array {
  $row=$this->require($id,$principal);if((string)$row->get('status')!==ChangeState::PENDING_APPROVAL)throw new \RuntimeException('Change is not pending approval.');$row->set('status',ChangeState::REJECTED);$row->set('rejection_reason',$reason);$row->set('updated_at',gmdate('Y-m-d H:i:s'));if(!$row->save())throw new \RuntimeException('Change rejection failed.');(new AuditService($this->modx))->record('change_rejected',['profile_id'=>(int)$row->get('profile_id'),'change_id'=>$id,'actor_type'=>$principal['type']??'unknown','actor_id'=>$principal['id']??'','reason'=>$reason]);return $row->toArray();
 }
 public function get(int $id):?array{$r=$this->modx->getObject(\AIBridge\Model\ChangeRequest::class,$id);return $r?$r->toArray():null;}
 private function transition(int $id,string $to,array $principal,string $event):array{$row=$this->require($id,$principal);$from=(string)$row->get('status');if(!ChangeState::canTransition($from,$to))throw new \RuntimeException('Invalid change state transition.');$row->set('status',$to);$row->set('updated_at',gmdate('Y-m-d H:i:s'));if(!$row->save())throw new \RuntimeException('Change state update failed.');(new AuditService($this->modx))->record($event,['profile_id'=>(int)$row->get('profile_id'),'change_id'=>$id,'actor_type'=>$principal['type']??'unknown','actor_id'=>$principal['id']??'']);return $row->toArray();}
 private function require(int $id,array $principal=[]):\xPDOObject{$row=$this->modx->getObject(\AIBridge\Model\ChangeRequest::class,$id);if(!$row)throw new \RuntimeException('Change request not found.');$profile=(int)($principal['profile_id']??0);if($profile>0&&(int)$row->get('profile_id')!==$profile)throw new \RuntimeException('Change request belongs to another profile.');return $row;}
}
