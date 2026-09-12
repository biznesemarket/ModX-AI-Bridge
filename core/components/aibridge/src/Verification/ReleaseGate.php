<?php
declare(strict_types=1);
namespace AIBridge\Verification;
final class ReleaseGate {
 public function evaluate(array $checks):array{$failed=[];foreach($checks as $name=>$result){$ok=is_array($result)?(bool)($result['ok']??false):(bool)$result;if(!$ok)$failed[]=(string)$name;}return ['passed'=>$failed===[],'failed'=>$failed,'checks'=>$checks];}
 public function requirePass(array $checks):void{$r=$this->evaluate($checks);if(!$r['passed'])throw new \RuntimeException('Release gate failed: '.implode(', ',$r['failed']));}
}
