<?php
declare(strict_types=1);
namespace AIBridge\Workflow;
use AIBridge\Queue\Job;
use AIBridge\Queue\QueueManager;
use AIBridge\Audit\AuditService;
use MODX\Revolution\modX;
final class ChangeExecutionService {
 public function __construct(private readonly modX $modx) {}
 public function dispatchApproved(int $changeId,array $principal):array {
  $service=new ChangeRequestService($this->modx); $change=$service->get($changeId); if(!$change) throw new \RuntimeException('Change request not found.');
  if((string)$change['status']!==ChangeState::APPROVED) throw new \RuntimeException('Only approved changes may execute.');
  $profile=(int)($principal['profile_id']??0); if($profile>0&&(int)$change['profile_id']!==$profile) throw new \RuntimeException('Change request belongs to another profile.');
  $payload=json_decode((string)$change['input_json'],true); if(!is_array($payload)) throw new \RuntimeException('Change payload is invalid.');
  $requestId=(string)$change['request_id']; $idem='change-'.$changeId.'-'.substr(hash('sha256',(string)$changeId),0,32);
  $job=new Job('resource_execution',['operation'=>$change['operation'],'input'=>$payload,'principal'=>$principal,'request'=>['request_id'=>$requestId,'idempotency_key'=>$idem,'channel'=>'manager','ip'=>'manager','approval_id'=>(int)$change['approval_id'],'change_id'=>$changeId]],3,300,$idem,(string)($principal['id']??''),$requestId,null,(int)$change['profile_id']);
  $jobId=(new QueueManager($this->modx))->dispatch($job);
  $row=$this->modx->getObject(\AIBridge\Model\ChangeRequest::class,$changeId); $row->set('status',ChangeState::EXECUTING); $row->set('job_id',$jobId); $row->set('updated_at',gmdate('Y-m-d H:i:s')); $row->save();
  (new AuditService($this->modx))->record('change_execution_dispatched',['profile_id'=>(int)$change['profile_id'],'change_id'=>$changeId,'job_id'=>$jobId,'actor_type'=>$principal['type']??'unknown','actor_id'=>$principal['id']??'']);
  return ['change_id'=>$changeId,'job_id'=>$jobId,'status'=>ChangeState::EXECUTING];
 }
}
