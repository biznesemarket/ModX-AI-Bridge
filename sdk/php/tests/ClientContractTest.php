<?php
declare(strict_types=1);

use AIBridge\SDK\ApiException;
use AIBridge\SDK\Client;
use AIBridge\SDK\HttpClient;
use AIBridge\SDK\Idempotency;
use PHPUnit\Framework\TestCase;

final class RecordingHttpClient implements HttpClient
{
    public array $calls = [];
    public function __construct(private array $responses = []) {}
    public function request(string $method, string $path, array $headers = [], ?array $json = null): array
    {
        $this->calls[] = ['method' => $method, 'path' => $path, 'headers' => $headers, 'json' => $json];
        return array_shift($this->responses) ?? ['ok' => true];
    }
}

final class ClientContractTest extends TestCase
{
    public function testCreateResourceCarriesIdempotencyHeader(): void
    {
        $http = new RecordingHttpClient();
        (new Client($http))->createResource(['pagetitle' => 'x'], 'idem-123');
        self::assertSame('POST', $http->calls[0]['method']);
        self::assertSame('/api/ai/v2/resources', $http->calls[0]['path']);
        self::assertSame('idem-123', $http->calls[0]['headers']['Idempotency-Key']);
        self::assertSame(['pagetitle' => 'x'], $http->calls[0]['json']);
    }

    public function testUpdateAndDeleteUseResourcePathAndIdempotency(): void
    {
        $http = new RecordingHttpClient();
        $client = new Client($http);
        $client->updateResource(17, ['pagetitle' => 'y'], 'idem-u');
        $client->deleteResource(17, 'idem-d');

        self::assertSame(['PATCH', '/api/ai/v2/resources/17'], [$http->calls[0]['method'], $http->calls[0]['path']]);
        self::assertSame('idem-u', $http->calls[0]['headers']['Idempotency-Key']);
        self::assertSame(17, $http->calls[0]['json']['id']);
        self::assertSame(['DELETE', '/api/ai/v2/resources/17'], [$http->calls[1]['method'], $http->calls[1]['path']]);
        self::assertSame('idem-d', $http->calls[1]['headers']['Idempotency-Key']);
    }

    public function testPublishAndPreviewAndValidateContracts(): void
    {
        $http = new RecordingHttpClient();
        $client = new Client($http);
        $client->publishResource(9, '55', 'idem-p');
        $client->previewResource(['id' => 9]);
        $client->validateContent(['pagetitle' => 'a'], ['fields' => []]);

        self::assertSame('/api/ai/v2/resources/9/publish', $http->calls[0]['path']);
        self::assertSame('55', $http->calls[0]['json']['approval_id']);
        self::assertSame('idem-p', $http->calls[0]['headers']['Idempotency-Key']);
        self::assertSame(['POST', '/api/ai/v2/resources/preview'], [$http->calls[1]['method'], $http->calls[1]['path']]);
        self::assertSame(['POST', '/api/ai/v2/content/validate'], [$http->calls[2]['method'], $http->calls[2]['path']]);
        self::assertArrayHasKey('content', $http->calls[2]['json']);
        self::assertArrayHasKey('contract', $http->calls[2]['json']);
    }

    public function testReadContracts(): void
    {
        $http = new RecordingHttpClient();
        $client = new Client($http);
        $client->capabilities();
        $client->profiles();
        $client->siteSchema(['limit' => 100]);
        $client->siteFingerprint();
        $client->contentContract();
        $client->contentContract(12);
        $client->job('42');
        $client->getResource(23);

        $paths = array_column($http->calls, 'path');
        self::assertSame('/api/ai/v2/capabilities', $paths[0]);
        self::assertSame('/api/ai/v2/profiles', $paths[1]);
        self::assertStringStartsWith('/api/ai/v2/site/schema?', $paths[2]);
        self::assertSame('/api/ai/v2/site/fingerprint', $paths[3]);
        self::assertSame('/api/ai/v2/content/contract', $paths[4]);
        self::assertSame('/api/ai/v2/content/contract?template_id=12', $paths[5]);
        self::assertSame('/api/ai/v2/jobs/42', $paths[6]);
        self::assertSame(['GET', '/api/ai/v2/resources/23'], [$http->calls[7]['method'], $paths[7]]);
    }

    public function testWaitForJobReturnsTerminalState(): void
    {
        $http = new RecordingHttpClient([
            ['data' => ['status' => 'running']],
            ['data' => ['status' => 'completed', 'job_id' => 5]],
        ]);
        $result = (new Client($http))->waitForJob('5', 5, 1);
        self::assertSame('completed', $result['data']['status']);
    }

    public function testWaitForJobReadsTheJobEnvelope(): void
    {
        $http = new RecordingHttpClient([
            ['success' => true, 'job' => ['status' => 'running']],
            ['success' => true, 'job' => ['status' => 'completed']],
        ]);
        $result = (new Client($http))->waitForJob('9', 5, 1);
        self::assertSame('completed', $result['job']['status']);
    }

    public function testWaitForJobTimesOutWithTypedError(): void
    {
        $http = new RecordingHttpClient(array_fill(0, 5, ['data' => ['status' => 'queued']]));
        try {
            (new Client($http))->waitForJob('7', 1, 1);
            self::fail('Expected a job_timeout ApiException.');
        } catch (ApiException $e) {
            self::assertSame(408, $e->status);
            self::assertSame('job_timeout', $e->errorCode);
            self::assertSame('7', $e->details['job_id']);
        }
    }

    public function testIdempotencyKeyIsRandomHex(): void
    {
        $key = Idempotency::key();
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $key);
        self::assertNotSame($key, Idempotency::key());
    }
}
