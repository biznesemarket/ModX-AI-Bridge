import { test } from 'node:test';
import assert from 'node:assert/strict';

import { BridgeClient, McpClient, ApiError } from '../../dist/src/index.js';

const rawContext = process.env.AIBRIDGE_LIVE_CONTEXT;
if (!rawContext) {
  throw new Error('AIBRIDGE_LIVE_CONTEXT is required; run bash scripts/ts-live-check.sh');
}
const context = JSON.parse(rawContext);

const client = new BridgeClient({ baseUrl: context.base_url, token: context.token, timeoutMs: 15000 });
const tvExplicit = 'live-tv-explicit-' + Date.now().toString(36);
let resourceId = 0;
let createdAlias = '';

function parseJobResult(job) {
  const raw = job.job?.result_json ?? job.result_json;
  return typeof raw === 'string' ? JSON.parse(raw) : raw;
}

test('health is public and reports the running version', async () => {
  const health = await client.request('GET', '/api/ai/v2/health');
  assert.equal(health.status, 'ok');
  assert.equal(health.version, '0.8.0');
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
  assert.ok(response.data.contexts.some((item) => item.key === 'web'));
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
  const filtered = await client.siteSchema({ limit: 1, context_key: 'web' });
  assert.equal(filtered.success, true);
  assert.equal(filtered.data.contract.resources.length, 1);
  assert.equal(filtered.data.contract.resources[0].context_key, 'web');
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
  createdAlias = alias;
  const queued = await client.createResource(
    {
      pagetitle: 'TS live E2E page',
      alias,
      template: context.template_id,
      content: '<h1>TS live E2E page</h1><p>created by the TypeScript SDK live test</p>',
      tvs: { [context.tv_name]: tvExplicit },
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

test('resource read-back exposes the updated state and template variables', async () => {
  assert.ok(resourceId > 0, 'create test must run first');
  const response = await client.getResource(resourceId);
  assert.equal(response.success, true);
  assert.equal(response.data.resource.id, resourceId);
  assert.equal(response.data.resource.pagetitle, 'TS live E2E page updated');
  assert.equal(response.data.resource.template, context.template_id);
  assert.equal(response.data.resource.tvs[context.tv_name], tvExplicit);
});

test('resource read-back returns 404 for a missing resource', async () => {
  await assert.rejects(client.getResource(99999999), (error) => {
    assert.equal(error.status, 404);
    assert.equal(error.code, 'resource_not_found');
    return true;
  });
});

test('resource list returns the created resource with filters', async () => {
  assert.ok(resourceId > 0, 'create test must run first');
  const response = await client.listResources({ q: createdAlias, limit: 5, template: context.template_id });
  assert.equal(response.success, true);
  assert.ok(response.data.total >= 1);
  assert.ok(response.data.resources.some((item) => item.id === resourceId));
  assert.equal(typeof response.data.count, 'number');
});

test('resource list filters by template variable', async () => {
  assert.ok(resourceId > 0, 'create test must run first');
  const response = await client.listResources({ tv_name: context.tv_name, tv_value: tvExplicit, limit: 5 });
  assert.equal(response.success, true);
  assert.ok(response.data.resources.some((item) => item.id === resourceId));

  const missing = await client.listResources({ tv_name: context.tv_name, tv_value: 'live-tv-missing-' + Date.now().toString(36) });
  assert.equal(missing.data.total, 0);
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
  assert.equal(init.result.serverInfo.version, '0.8.0');

  const tools = await mcp.tools();
  assert.ok(tools.result.tools.some((tool) => tool.name === 'resource_create'));

  const call = await mcp.callTool('content_validate', {
    content: { pagetitle: 'MCP live validation' },
    contract: { fields: { pagetitle: { type: 'string', required: true } } },
  });
  assert.equal(JSON.parse(call.result.content[0].text).valid, true);

  const readBack = await mcp.callTool('resource_read', { id: resourceId });
  const readBackPayload = JSON.parse(readBack.result.content[0].text);
  assert.equal(readBackPayload.data.resource.pagetitle, 'TS live E2E page updated');
  assert.equal(readBackPayload.data.resource.tvs[context.tv_name], tvExplicit);

  const listBack = await mcp.callTool('resource_list', { q: createdAlias, tv_name: context.tv_name, tv_value: tvExplicit, limit: 5 });
  const listPayload = JSON.parse(listBack.result.content[0].text);
  assert.ok(listPayload.data.resources.some((item) => item.id === resourceId));

  const schemaBack = await mcp.callTool('site_schema', { context_key: 'web', limit: 1 });
  const schemaPayload = JSON.parse(schemaBack.result.content[0].text);
  assert.equal(schemaPayload.contract.resources.length, 1);

  const templates = await mcp.resourceTemplates();
  assert.ok(templates.result.resourceTemplates.some((template) => template.uriTemplate === 'modx://resource/{id}'));

  const templateRead = await mcp.readResource('modx://resource/' + resourceId);
  const templatePayload = JSON.parse(templateRead.result.contents[0].text);
  assert.equal(templatePayload.data.resource.pagetitle, 'TS live E2E page updated');
  assert.equal(templatePayload.data.resource.tvs[context.tv_name], tvExplicit);

  const missingRead = await mcp.readResource('modx://resource/99999999');
  assert.equal(missingRead.error.code, -32002);

  const fingerprint = await mcp.readResource('modx://site/fingerprint');
  assert.ok(fingerprint.result.contents[0].text.length > 0);
});
