<?php
declare(strict_types=1);
namespace AIBridge\SDK;
interface HttpClient { public function request(string $method,string $path,array $headers=[],?array $json=null): array; }
final class CurlHttpClient implements HttpClient {
 public function __construct(private readonly string $baseUrl, private readonly string $token, private readonly int $timeout=30) {}
 public function request(string $method,string $path,array $headers=[],?array $json=null): array {
  $url=rtrim($this->baseUrl,'/').'/'.ltrim($path,'/'); $ch=curl_init($url); if($ch===false) throw new ApiException('Unable to initialize HTTP client');
  $body=$json===null?null:json_encode($json,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
  $h=['Accept: application/json','Authorization: Bearer '.$this->token,'Content-Type: application/json','User-Agent: modx-ai-bridge-sdk-php/0.4']; foreach($headers as $k=>$v)$h[]=$k.': '.$v;
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>strtoupper($method),CURLOPT_HTTPHEADER=>$h,CURLOPT_TIMEOUT=>$this->timeout,CURLOPT_FOLLOWLOCATION=>false]); if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
  $raw=curl_exec($ch); if($raw===false){$e=curl_error($ch);curl_close($ch);throw new ApiException('HTTP transport failed: '.$e);} $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  $data=$raw===''?[]:json_decode($raw,true); if(!is_array($data))$data=['raw'=>$raw]; if($status>=400){$err=is_array($data['error']??null)?$data['error']:[]; throw new ApiException((string)($err['message']??'API request failed'),$status,$err['code']??null,$err['details']??[]);} return $data;
 }
}
