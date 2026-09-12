<?php
declare(strict_types=1);
namespace AIBridge\Verification;
use AIBridge\Security\SecretRedactor;
use MODX\Revolution\modX;
final class VerificationService {
 public function __construct(private readonly modX $modx, private readonly SecretRedactor $redactor=new SecretRedactor()) {}
 public function verifyResource(int $resourceId,array $expected): VerificationResult {
  $resource=$this->modx->getObject('modResource',['id'=>$resourceId]);
  if(!$resource) return new VerificationResult(false,$this->safe($expected),[],[['path'=>'id','type'=>'missing_resource','expected'=>$resourceId]]);
  $actual=$resource->toArray(); if(isset($expected['tvs'])&&is_array($expected['tvs']))$actual['tvs']=$this->readTvs($resource);
  $safeExpected=$this->safe($expected);$safeActual=$this->safe($actual);$m=$this->diff($safeExpected,$safeActual);
  return new VerificationResult($m===[],$safeExpected,$safeActual,$m);
 }
 public function verifyDeleted(int $resourceId): VerificationResult { $resource=$this->modx->getObject('modResource',['id'=>$resourceId]); if($resource && !(int)$resource->get('deleted')) return new VerificationResult(false,['deleted'=>true],['deleted'=>false],[['path'=>'deleted','type'=>'mismatch','expected'=>true,'actual'=>false]]); return new VerificationResult(true,['deleted'=>true],['deleted'=>true]); }
 public function verifyChange(array $change): VerificationResult { $id=(int)($change['resource_id']??0);$expected=json_decode((string)($change['after_json']??'{}'),true);if(!is_array($expected))throw new \InvalidArgumentException('Change expected state is invalid.');if($id<1)return new VerificationResult(false,$this->safe($expected),[],[['path'=>'resource_id','type'=>'missing']]);return $this->verifyResource($id,$expected); }
 private function readTvs(\xPDOObject $resource):array{$out=[];$tvs=$resource->getMany('TemplateVars');if(is_array($tvs))foreach($tvs as $tv)if(method_exists($tv,'get'))$out[(string)$tv->get('name')]=$tv->getValue($resource->get('id'));return $out;}
 private function safe(array $v):array{return $this->redactor->redactRecursive($v);}
 private function diff(array $a,array $b,string $path=''):array{$out=[];foreach(array_keys($a) as $k){$p=$path===''?(string)$k:$path.'.'.$k;if(!array_key_exists($k,$b)){$out[]=['path'=>$p,'type'=>'missing','expected'=>$a[$k]];continue;}if(is_array($a[$k])&&is_array($b[$k])){$out=array_merge($out,$this->diff($a[$k],$b[$k],$p));continue;}if($a[$k]!==$b[$k])$out[]=['path'=>$p,'type'=>'mismatch','expected'=>$a[$k],'actual'=>$b[$k]];}return $out;}
}
