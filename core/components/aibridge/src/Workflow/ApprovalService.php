<?php
declare(strict_types=1);
namespace AIBridge\Workflow;
use MODX\Revolution\modX;
final class ApprovalService {
 public function __construct(private readonly modX $modx) {}
 public function create(int $changeId,array $approver): array {
  $row=$this->modx->newObject(\AIBridge\Model\Approval::class);
  $now=gmdate('Y-m-d H:i:s');
  $row->fromArray(['change_id'=>$changeId,'status'=>'pending','requested_by'=>(string)($approver['id']??''),'requested_at'=>$now]);
  if(!$row->save()) throw new \RuntimeException('Approval persistence failed.');
  return $row->toArray();
 }
 public function decide(int $approvalId,string $decision,array $actor,string $comment=''): array {
  if(!in_array($decision,['approved','rejected'],true)) throw new \InvalidArgumentException('Invalid approval decision.');
  $row=$this->modx->getObject(\AIBridge\Model\Approval::class,$approvalId);
  if(!$row) throw new \RuntimeException('Approval not found.');
  if((string)$row->get('status')!=='pending') throw new \RuntimeException('Approval is not pending.');
  $row->fromArray(['status'=>$decision,'decided_by'=>(string)($actor['id']??''),'decided_at'=>gmdate('Y-m-d H:i:s'),'comment'=>$comment]);
  if(!$row->save()) throw new \RuntimeException('Approval update failed.');
  return $row->toArray();
 }
 public function isApproved(int $approvalId,int $changeId):bool {
  $row=$this->modx->getObject(\AIBridge\Model\Approval::class,['id'=>$approvalId,'change_id'=>$changeId,'status'=>'approved']);
  return $row!==null;
 }
}
