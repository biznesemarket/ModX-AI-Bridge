<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase; use AIBridge\SDK\Client; use AIBridge\SDK\HttpClient;
final class ClientContractTest extends TestCase { public function testResourceCreateCarriesIdempotencyHeader():void{$http=new class implements HttpClient{public array $call=[];public function request(string $m,string $p,array $h=[],?array $j=null):array{$this->call=[$m,$p,$h,$j];return ['ok'=>true];}};$c=new Client($http);$c->createResource(['pagetitle'=>'x'],'idem-123');$this->assertSame('POST',$http->call[0]);$this->assertSame('idem-123',$http->call[2]['Idempotency-Key']);} }
