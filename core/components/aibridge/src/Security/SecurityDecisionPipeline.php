<?php
declare(strict_types=1);
namespace AIBridge\Security;
use AIBridge\Configuration\BridgeConfig;
use AIBridge\Services\PolicyService;
use MODX\Revolution\modX;
final class SecurityDecisionPipeline {
 public function __construct(private readonly BridgeConfig $config,private readonly Authorization $authorization,private readonly IpAllowlist $ipAllowlist,private readonly PolicyService $policyService,private readonly ?modX $modx=null) {}
 public function decide(array $request,array $principal,string $operation):SecurityDecision {
  $ip=(string)($request['ip']??'');$allow=$this->config->get('ip_allowlist',[]);$managerChannel=(($principal['type']??'')==='manager'&&($principal['manager_authorized']??false)===true&&($request['channel']??'')==='manager');
  if(!$managerChannel&&!$this->ipAllowlist->allows($ip,is_array($allow)?$allow:[]))return new SecurityDecision(false,'ip_not_allowed',['source_ip']);
  if(!$this->authorization->allows($principal,$operation,$request))return new SecurityDecision(false,'insufficient_scope',['scope']);
  if($operation==='resource.delete'&&!$this->config->isEnabled('allow_resource_delete'))return new SecurityDecision(false,'operation_disabled',['resource_delete']);
  if($operation==='settings.write'&&!$this->config->isEnabled('allow_setting_write'))return new SecurityDecision(false,'operation_disabled',['settings_write']);
  if($operation==='resource.publish'&&$this->config->isEnabled('approval_required_for_publish')){
   $approvalId=(int)($request['approval_id']??0);$changeId=(int)($request['change_id']??0);if($approvalId<1||$changeId<1)return new SecurityDecision(false,'approval_required',['publish_approval']);
   if(!$this->isApprovedForChange($approvalId,$changeId))return new SecurityDecision(false,'approval_invalid',['publish_approval']);
  }
  $policy=$this->policyService->authorize($operation,$request+$principal);if(empty($policy['allowed']))return new SecurityDecision(false,(string)($policy['code']??'policy_denied'),$policy['reasons']??[]);return new SecurityDecision(true,'allowed');
 }
 private function isApprovedForChange(int $approvalId,int $changeId):bool {if(!$this->modx)return false;$row=$this->modx->getObject(\AIBridge\Model\Approval::class,['id'=>$approvalId,'change_id'=>$changeId,'status'=>'approved']);return $row!==null;}
}
