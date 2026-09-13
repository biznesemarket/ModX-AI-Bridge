<?php
declare(strict_types=1);
namespace AIBridge\SDK;
final class Client {
 public function __construct(private readonly HttpClient $http) {}
 public function capabilities(): array { return $this->http->request('GET','/api/ai/v2/capabilities'); }
 public function profiles(): array { return $this->http->request('GET','/api/ai/v2/profiles'); }
 public function siteSchema(array $params=[]): array { return $this->http->request('GET','/api/ai/v2/site/schema'.($params?'?'.http_build_query($params):'')); }
 public function siteFingerprint(): array { return $this->http->request('GET','/api/ai/v2/site/fingerprint'); }
 public function contentContract(?int $templateId=null): array { $q=$templateId===null?'':'?template_id='.$templateId; return $this->http->request('GET','/api/ai/v2/content/contract'.$q); }
 public function validateContent(array $content,array $contract): array { return $this->http->request('POST','/api/ai/v2/content/validate',[],['content'=>$content,'contract'=>$contract]); }
 public function createResource(array $resource,string $idempotencyKey): array { return $this->http->request('POST','/api/ai/v2/resources', ['Idempotency-Key'=>$idempotencyKey],$resource); }
 public function getResource(int $id): array { return $this->http->request('GET','/api/ai/v2/resources/'.$id); }
 public function updateResource(int $id,array $resource,string $idempotencyKey): array { $resource['id']=$id; return $this->http->request('PATCH','/api/ai/v2/resources/'.$id,['Idempotency-Key'=>$idempotencyKey],$resource); }
 public function deleteResource(int $id,string $idempotencyKey): array { return $this->http->request('DELETE','/api/ai/v2/resources/'.$id,['Idempotency-Key'=>$idempotencyKey]); }
 public function previewResource(array $resource): array { return $this->http->request('POST','/api/ai/v2/resources/preview',[], $resource); }
 public function publishResource(int $id,string $approvalId,string $idempotencyKey): array { return $this->http->request('POST','/api/ai/v2/resources/'.$id.'/publish',['Idempotency-Key'=>$idempotencyKey],['approval_id'=>$approvalId]); }
 public function job(string $id): array { return $this->http->request('GET','/api/ai/v2/jobs/'.$id); }
 public function waitForJob(string $id,int $timeoutSeconds=60,int $pollSeconds=1): array { $deadline=time()+$timeoutSeconds; do{$job=$this->job($id);$status=(string)($job['data']['status']??$job['job']['status']??$job['status']??'');if(in_array($status,['completed','failed','cancelled'],true))return $job;sleep(max(1,$pollSeconds));}while(time()<$deadline); throw new ApiException('Job polling timeout',408,'job_timeout',['job_id'=>$id]); }
 public function mcp(McpClient $mcp): McpClient { return $mcp; }
}
final class McpClient {
 public function __construct(private readonly HttpClient $http) {}
 public function initialize(array $clientInfo=['name'=>'modx-ai-bridge-sdk','version'=>'0.2.0']): array { return $this->call('initialize',['clientInfo'=>$clientInfo]); }
 public function tools(): array { return $this->call('tools/list'); }
 public function callTool(string $name,array $arguments=[]): array { return $this->call('tools/call',['name'=>$name,'arguments'=>$arguments]); }
 public function resources(): array { return $this->call('resources/list'); }
 public function readResource(string $uri): array { return $this->call('resources/read',['uri'=>$uri]); }
 public function prompts(): array { return $this->call('prompts/list'); }
 public function getPrompt(string $name,array $arguments=[]): array { return $this->call('prompts/get',['name'=>$name,'arguments'=>$arguments]); }
 private function call(string $method,array $params=[]): array { static $id=0; return $this->http->request('POST','/api/ai/v2/mcp',[],['jsonrpc'=>'2.0','id'=>++$id,'method'=>$method,'params'=>$params]); }
}
