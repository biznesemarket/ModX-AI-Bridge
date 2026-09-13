import { test } from 'node:test';
import assert from 'node:assert/strict';

import { BridgeClient, McpClient, ApiError } from '../../dist/src/index.js';

const rawContext = process.env.AIBRIDGE_LIVE_CONTEXT;
if (!rawContext) {
  throw new Error('AIBRIDGE_LIVE_CONTEXT is required; run bash scripts/ts-live-check.sh');
}
const context = JSON.parse(rawContext);

const client = new BridgeClient({ baseUrl: context.base_url, token: context.token, timeoutMs: 15000 });
let resourceId = 0;

function parseJobResult(job) {
  const raw = job.job?.result_json ?? job.result_json;
  return typeof raw === 'string' ? JSON.parse(raw) : raw;
}

test('health is public and reports the running version', async () => {
  const health = await client.request('GET', '/api/ai/v2/health');
  assert.equal(health.status, 'ok');
  assert.equal(health.version, '0.1.2');
});

test('capabilities rejects an unknown token over HTTP', async () => {
  const bad = new BridgeClient({ baseUrl: context.base_url, token: 'not-a-real-token' });
  await assert.rejects(bad.capabilities(), (error) => {
    assert.ok(error instanceof ApiError);
    assert.equal(error.status, 401);
    assert.equal(error.code, 'authentication_failed');
    return true;
  });
});

test('capabilities with the scoped token exposes REST and MCP', async () => {
  const response = await client.capabilities();
  assert.equal(response.success, true);
  assert.equal(response.data.interfaces.rest.version, 'v2');
  assert.equal(response.data.interfaces.mcp.protocolVersion, '2025-06-18');
  assert.ok(response.data.capabilities.length > 0);
});

test('profiles are isolated to the live profile', async () => {
  const response = await client.profiles();
  assert.equal(response.success, true);
  assert.equal(response.data.profiles.length, 1);
  assert.equal(response.data.profiles[0].id, context.profile_id);
  assert.equal(response.data.profiles[0].site_key, context.site_key);
});

test('site schema and fingerprint are discoverable', async () => {
  const schema = await client.siteSchema();
  assert.equal(schema.success, true);
  assert.ok(schema.data);
  const fingerprint = await client.siteFingerprint();
  assert.equal(fingerprint.success, true);
  assert.ok(fingerprint.data.fingerprint);
});

test('content contract is built for a template', async () => {
  const contract = await client.contentContract(context.template_id);
  assert.equal(contract.success, true);
  assert.ok(contract.data);
});

test('content validation accepts valid content and reports errors', async () => {
  const valid = await client.validateContent(
    { pagetitle: 'TS live E2E page', content: '<h1>TS live E2E</h1>' },
    { fields: { pagetitle: { type: 'string', required: true, max_length: 255 } } },
  );
  assert.equal(valid.data.valid, true);

  const invalid = await client.validateContent(
    { content: '<p>no title</p>' },
    { fields: { pagetitle: { type: 'string', required: true } } },
  );
  assert.equal(invalid.data.valid, false);
  assert.ok(invalid.data.errors.length > 0);
});

test('resource create flows through the queue and returns a resource id', async () => {
  const alias = 'ts-live-' + Date.now().toString(36);
  const queued = await client.createResource(
    {
      pagetitle: 'TS live E2E page',
      alias,
      template: context.template_id,
      content: '<h1>TS live E2E page</h1><p>created by the TypeScript SDK live test</p>',
    },
    'ts-live-create-' + alias,
  );
  assert.equal(queued.success, true);
  assert.equal(queued.status, 'queued');
  assert.ok(queued.job_id > 0);

  const job = await client.waitForJob(queued.job_id, 30000, 250);
  assert.equal(job.job.status, 'completed');
  const result = parseJobResult(job);
  assert.equal(result.success, true);
  resourceId = Number(result.data.resource_id);
  assert.ok(resourceId > 0);
});

test('resource update flows through the queue', async () => {
  assert.ok(resourceId > 0, 'create test must run first');
  const queued = await client.updateResource(
    resourceId,
    { pagetitle: 'TS live E2E page updated' },
    'ts-live-update-' + resourceId,
  );
  assert.equal(queued.status, 'queued');
  const job = await client.waitForJob(queued.job_id, 30000, 250);
  assert.equal(job.job.status, 'completed');
  assert.equal(parseJobResult(job).success, true);
});

test('delete stays disabled and publish stays approval-gated', async () => {
  assert.ok(resourceId > 0, 'create test must run first');
  await assert.rejects(client.deleteResource(resourceId, 'ts-live-delete-' + resourceId), (error) => {
    assert.equal(error.status, 403);
    assert.equal(error.code, 'operation_disabled');
    return true;
  });
  await assert.rejects(client.publishResource(resourceId, 'missing-approval', 'ts-live-publish-' + resourceId), (error) => {
    assert.equal(error.status, 403);
    assert.equal(error.code, 'approval_required');
    return true;
  });
});

test('MCP initialize, tools, tool call and resource read work over HTTP', async () => {
  const mcp = new McpClient(client);
  const init = await mcp.initialize();
  assert.equal(init.result.serverInfo.name, 'modx-ai-bridge');
  assert.equal(init.result.serverInfo.version, '0.1.2');

  const tools = await mcp.tools();
  assert.ok(tools.result.tools.some((tool) => tool.name === 'resource_create'));

  const call = await mcp.callTool('content_validate', {
    content: { pagetitle: 'MCP live validation' },
    contract: { fields: { pagetitle: { type: 'string', required: true } } },
  });
  assert.equal(JSON.parse(call.result.content[0].text).valid, true);

  const fingerprint = await mcp.readResource('modx://site/fingerprint');
  assert.ok(fingerprint.result.contents[0].text.length > 0);
});
