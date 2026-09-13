<?php
declare(strict_types=1);
namespace AIBridge\MCP;

final class McpProtocol
{
    public const PROTOCOL_VERSION='2025-06-18';
    public function initialize(array $clientInfo=[]): array { return ['protocolVersion'=>self::PROTOCOL_VERSION,'capabilities'=>['tools'=>['listChanged'=>false],'resources'=>['subscribe'=>false,'listChanged'=>false],'prompts'=>['listChanged'=>false]],'serverInfo'=>['name'=>'modx-ai-bridge','version'=>'0.7.0']]; }
    public function error(int|string|null $id,int $code,string $message,array $data=[]): array { $r=['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>$code,'message'=>$message]]; if($data)$r['error']['data']=$data; return $r; }
    public function result(int|string|null $id,array $result): array { return ['jsonrpc'=>'2.0','id'=>$id,'result'=>$result]; }
}
