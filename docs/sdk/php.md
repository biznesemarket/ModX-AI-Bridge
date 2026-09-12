# PHP SDK

```php
use AIBridge\SDK\{Client,CurlHttpClient,Idempotency};

$client = new Client(new CurlHttpClient('https://example.com', getenv('AIBRIDGE_TOKEN')));
$schema = $client->siteSchema(['limit'=>100]);
$result = $client->createResource(['pagetitle'=>'AI article','content'=>'<p>...</p>'], Idempotency::key());
```

For custom HTTP stacks implement `AIBridge\SDK\HttpClient`.
