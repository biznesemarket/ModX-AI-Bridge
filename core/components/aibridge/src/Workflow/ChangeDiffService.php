<?php
declare(strict_types=1);
namespace AIBridge\Workflow;
use AIBridge\Security\SecretRedactor;
use MODX\Revolution\modX;
final class ChangeDiffService {
 public function __construct(private readonly modX $modx, private readonly SecretRedactor $redactor=new SecretRedactor()) {}
 public function build(?int $resourceId,array $candidate): array {
  $before=[]; if($resourceId){$r=$this->modx->getObject(\MODX\Revolution\modResource::class,['id'=>$resourceId,'deleted'=>0]); if($r)$before=$r->toArray();}
  $safeBefore=$this->redactor->redactRecursive($before); $safeAfter=$this->redactor->redactRecursive($candidate);
  return ['before'=>$safeBefore,'after'=>$safeAfter,'changes'=>$this->diff($safeBefore,$safeAfter)];
 }
 private function diff(array $a,array $b,string $path=''):array { $out=[]; foreach(array_unique(array_merge(array_keys($a),array_keys($b))) as $k){$p=$path===''?(string)$k:$path.'.'.$k;if(!array_key_exists($k,$a)){$out[]=['path'=>$p,'type'=>'added','to'=>$b[$k]];continue;}if(!array_key_exists($k,$b)){$out[]=['path'=>$p,'type'=>'removed','from'=>$a[$k]];continue;}if(is_array($a[$k])&&is_array($b[$k])){$out=array_merge($out,$this->diff($a[$k],$b[$k],$p));continue;}if($a[$k]!==$b[$k])$out[]=['path'=>$p,'type'=>'changed','from'=>$a[$k],'to'=>$b[$k]];}return $out;}
}
