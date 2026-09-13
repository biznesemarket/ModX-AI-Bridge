import { test } from 'node:test';
import assert from 'node:assert/strict';

import { BridgeClient, McpClient, ApiError } from '../../dist/src/index.js';

function jsonResponse(body, status = 200) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'content-type': 'application/json' },
  });
}

function recorder(factories) {
  const calls = [];
  let index = 0;
  const fetchImpl = async (input, init) => {
    calls.push({ url: String(input), init: init ?? {} });
    const factory = factories[Math.min(index, factories.length - 1)];
    index += 1;
    return factory();
  };
  return { calls, fetchImpl };
}

function makeClient(factories) {
  const { calls, fetchImpl } = recorder(factories);
  const client = new BridgeClient({ baseUrl: 'https://bridge.example/', token: 'tok', fetchImpl });
  return { client, calls };
}

function headersOf(call) {
  return call.init.headers ?? {};
}

test('request sends auth and JSON headers and trims the base URL', async () => {
  const { client, calls } = makeClient([() => jsonResponse({ ok: true })]);
  const data = await client.validateContent({ title: 'x' }, { type: 'object' });
  assert.deepEqual(data, { ok: true });
  assert.equal(calls.length, 1);
  assert.equal(calls[0].url, 'https://bridge.example/api/ai/v2/content/validate');
  assert.equal(calls[0].init.method, 'POST');
  const headers = headersOf(calls[0]);
  assert.equal(headers.Authorization, 'Bearer tok');
  assert.equal(headers.Accept, 'application/json');
  assert.equal(headers['Content-Type'], 'application/json');
  assert.equal(headers['User-Agent'], 'modx-ai-bridge-sdk-ts/0.7');
  assert.equal(
    calls[0].init.body,
    JSON.stringify({ content: { title: 'x' }, contract: { type: 'object' } }),
  );
});

test('createResource sends an Idempotency-Key', async () => {
  const { client, calls } = makeClient([() => jsonResponse({})]);
  await client.createResource({ pagetitle: 'P' }, 'idem-1');
  assert.equal(calls[0].url, 'https://bridge.example/api/ai/v2/resources');
  assert.equal(calls[0].init.method, 'POST');
  assert.equal(headersOf(calls[0])['Idempotency-Key'], 'idem-1');
  assert.equal(calls[0].init.body, JSON.stringify({ pagetitle: 'P' }));
});

test('updateResource merges the id into the body', async () => {
  const { client, calls } = makeClient([() => jsonResponse({})]);
  await client.updateResource(7, { pagetitle: 'U' }, 'idem-2');
  assert.equal(calls[0].url, 'https://bridge.example/api/ai/v2/resources/7');
  assert.equal(calls[0].init.method, 'PATCH');
  assert.equal(headersOf(calls[0])['Idempotency-Key'], 'idem-2');
  assert.equal(calls[0].init.body, JSON.stringify({ pagetitle: 'U', id: 7 }));
});

test('deleteResource sends no body', async () => {
  const { client, calls } = makeClient([() => jsonResponse({})]);
  await client.deleteResource(9, 'idem-3');
  assert.equal(calls[0].url, 'https://bridge.example/api/ai/v2/resources/9');
  assert.equal(calls[0].init.method, 'DELETE');
  assert.equal(calls[0].init.body, undefined);
  assert.equal(headersOf(calls[0])['Idempotency-Key'], 'idem-3');
});

test('getResource reads a resource by id and exposes template variables', async () => {
  const { client, calls } = makeClient([() => jsonResponse({ success: true, data: { resource: { id: 9, tvs: { rest_read_tv: 'rest-tv-value' } } } })]);
  const response = await client.getResource(9);
  assert.equal(calls[0].url, 'https://bridge.example/api/ai/v2/resources/9');
  assert.equal(calls[0].init.method, 'GET');
  assert.equal(calls[0].init.body, undefined);
  assert.equal(response.data.resource.id, 9);
  assert.equal(response.data.resource.tvs.rest_read_tv, 'rest-tv-value');
});

test('listResources builds a filter query string', async () => {
  const { client, calls } = makeClient([() => jsonResponse({ success: true, data: { resources: [], count: 0, total: 0, limit: 10, offset: 0 } })]);
  const response = await client.listResources({ q: 'page', limit: 10, published: true });
  assert.equal(calls[0].url, 'https://bridge.example/api/ai/v2/resources?q=page&limit=10&published=true');
  assert.equal(calls[0].init.method, 'GET');
  assert.equal(calls[0].init.body, undefined);
  assert.equal(response.data.count, 0);
});

test('publishResource sends the approval_id', async () => {
  const { client, calls } = makeClient([() => jsonResponse({})]);
  await client.publishResource(3, 'appr-1', 'idem-4');
  assert.equal(calls[0].url, 'https://bridge.example/api/ai/v2/resources/3/publish');
  assert.equal(calls[0].init.method, 'POST');
  assert.equal(headersOf(calls[0])['Idempotency-Key'], 'idem-4');
  assert.equal(calls[0].init.body, JSON.stringify({ approval_id: 'appr-1' }));
});

test('siteSchema and contentContract build query strings', async () => {
  const { client, calls } = makeClient([() => jsonResponse({}), () => jsonResponse({}), () => jsonResponse({})]);
  await client.siteSchema({ template_id: 3, q: 'a b' });
  await client.contentContract();
  await client.contentContract(5);
  assert.equal(calls[0].url, 'https://bridge.example/api/ai/v2/site/schema?template_id=3&q=a+b');
  assert.equal(calls[1].url, 'https://bridge.example/api/ai/v2/content/contract');
  assert.equal(calls[2].url, 'https://bridge.example/api/ai/v2/content/contract?template_id=5');
});

test('non-ok responses map to ApiError', async () => {
  const { client } = makeClient([
    () => jsonResponse({ error: { message: 'nope', code: 'forbidden', details: { why: 'x' } } }, 403),
  ]);
  await assert.rejects(client.capabilities(), (error) => {
    assert.ok(error instanceof ApiError);
    assert.equal(error.status, 403);
    assert.equal(error.code, 'forbidden');
    assert.equal(error.message, 'nope');
    assert.deepEqual(error.details, { why: 'x' });
    return true;
  });
});

test('non-JSON error bodies fall back to a generic message', async () => {
  const { client } = makeClient([
    () => new Response('boom', { status: 500, headers: { 'content-type': 'text/plain' } }),
  ]);
  await assert.rejects(client.capabilities(), (error) => {
    assert.ok(error instanceof ApiError);
    assert.equal(error.status, 500);
    assert.equal(error.message, 'API request failed');
    return true;
  });
});

test('waitForJob polls until a terminal state', async () => {
  const { client, calls } = makeClient([
    () => jsonResponse({ data: { status: 'queued' } }),
    () => jsonResponse({ data: { status: 'completed' } }),
  ]);
  const job = await client.waitForJob('job-1', 5000, 1);
  assert.equal(job.data?.status, 'completed');
  assert.equal(calls.length, 2);
});

test('waitForJob reads the real /jobs envelope (success.job.status)', async () => {
  const { client, calls } = makeClient([
    () => jsonResponse({ success: true, job: { id: 1, status: 'queued' } }),
    () => jsonResponse({ success: true, job: { id: 1, status: 'completed' } }),
  ]);
  const job = await client.waitForJob('1', 5000, 1);
  assert.equal(job.job.status, 'completed');
  assert.equal(calls.length, 2);
});

test('waitForJob throws a 408 ApiError on timeout', async () => {
  const { client } = makeClient([() => jsonResponse({ data: { status: 'queued' } })]);
  await assert.rejects(client.waitForJob('job-2', 20, 5), (error) => {
    assert.ok(error instanceof ApiError);
    assert.equal(error.status, 408);
    assert.equal(error.code, 'job_timeout');
    return true;
  });
});

test('McpClient wraps calls in JSON-RPC envelopes with incrementing ids', async () => {
  const { client, calls } = makeClient([
    () => jsonResponse({ jsonrpc: '2.0', id: 1, result: {} }),
    () => jsonResponse({ jsonrpc: '2.0', id: 2, result: {} }),
  ]);
  const mcp = new McpClient(client);
  await mcp.initialize();
  await mcp.callTool('resource_publish', { id: 1 });
  assert.equal(calls[0].url, 'https://bridge.example/api/ai/v2/mcp');
  const first = JSON.parse(String(calls[0].init.body));
  assert.equal(first.jsonrpc, '2.0');
  assert.equal(first.method, 'initialize');
  assert.equal(first.id, 1);
  assert.equal(first.params.clientInfo.version, '0.7.0');
  const second = JSON.parse(String(calls[1].init.body));
  assert.equal(second.method, 'tools/call');
  assert.equal(second.id, 2);
  assert.deepEqual(second.params, { name: 'resource_publish', arguments: { id: 1 } });
});
