<?php
declare(strict_types=1);
namespace AIBridge\Processors;
use AIBridge\Verification\VerificationService;
use MODX\Revolution\Processors\Processor;
final class ChangeVerificationProcessor extends Processor { public function process(){ $id=(int)$this->getProperty('change_id');if($id<1)return $this->failure('change_id is required.');$row=$this->modx->getObject(\AIBridge\Model\ChangeRequest::class,$id);if(!$row)return $this->failure('Change request not found.');$result=(new VerificationService($this->modx))->verifyChange($row->toArray())->toArray();return $result['valid']?$this->success('',$result):$this->failure('Post-execution verification failed.',$result); } }
return ChangeVerificationProcessor::class;
