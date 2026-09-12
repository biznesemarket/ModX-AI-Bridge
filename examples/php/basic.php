<?php
require __DIR__.'/../../sdk/php/src/ApiException.php';
require __DIR__.'/../../sdk/php/src/HttpClient.php';
require __DIR__.'/../../sdk/php/src/Idempotency.php';
require __DIR__.'/../../sdk/php/src/Client.php';
use AIBridge\SDK\{Client,CurlHttpClient,Idempotency};
$bridge=new Client(new CurlHttpClient(getenv('AIBRIDGE_URL'),getenv('AIBRIDGE_TOKEN')));
print_r($bridge->siteFingerprint());
print_r($bridge->createResource(['pagetitle'=>'Example'],Idempotency::key()));
