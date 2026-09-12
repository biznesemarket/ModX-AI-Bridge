<?php

declare(strict_types=1);

namespace AIBridge\Services;

use AIBridge\Contracts\DiscoveredSite;
use AIBridge\Inspectors\ChunkInspector;
use AIBridge\Inspectors\MIGXInspector;
use AIBridge\Inspectors\SiteInspector;
use AIBridge\Inspectors\SnippetInspector;
use AIBridge\Inspectors\TVInspector;
use AIBridge\Inspectors\TemplateInspector;
use MODX\Revolution\modX;

final class SiteIntelligenceService
{
    public function __construct(private readonly modX $modx)
    {
    }

    public function discover(array $input = []): array
    {
        $templates = (new TemplateInspector($this->modx))->inspect();
        $tvs = (new TVInspector($this->modx))->inspect();
        $chunks = (new ChunkInspector($this->modx))->inspect();
        $snippets = (new SnippetInspector($this->modx))->inspect();
        $site = (new SiteInspector($this->modx))->inspect();
        $migx = (new MIGXInspector($this->modx))->inspect($tvs);

        $resources = $this->resources((int) ($input['root_id'] ?? 0), (int) ($input['limit'] ?? 500));
        $contract = new DiscoveredSite('1.0', [
            'contract' => 'modx-ai-bridge/site',
            'version' => '1.0',
            'generated_at' => gmdate('c'),
            'site' => $site,
            'templates' => $templates,
            'template_variables' => $tvs,
            'chunks' => $chunks,
            'snippets' => $snippets,
            'migx' => $migx,
            'resources' => $resources,
        ]);

        $fingerprint = (new SiteFingerprintService())->fingerprint($contract);

        return [
            'contract' => $contract->toArray(),
            'contract_version' => $contract->version(),
            'fingerprint' => $fingerprint['fingerprint'],
        ];
    }

    private function resources(int $rootId, int $limit): array
    {
        $limit = max(1, min($limit, 5000));
        $where = $rootId > 0 ? ['parent' => $rootId] : [];
        $rows = [];
        foreach ($this->modx->getCollection('modResource', $where, [
            'sortby' => 'id', 'sortdir' => 'ASC', 'limit' => $limit,
        ]) as $resource) {
            $rows[] = [
                'id' => (int) $resource->get('id'),
                'parent' => (int) $resource->get('parent'),
                'pagetitle' => (string) $resource->get('pagetitle'),
                'longtitle' => (string) $resource->get('longtitle'),
                'description' => (string) $resource->get('description'),
                'alias' => (string) $resource->get('alias'),
                'uri' => (string) $resource->get('uri'),
                'published' => (bool) $resource->get('published'),
                'deleted' => (bool) $resource->get('deleted'),
                'hidemenu' => (bool) $resource->get('hidemenu'),
                'isfolder' => (bool) $resource->get('isfolder'),
                'template_id' => (int) $resource->get('template'),
                'context_key' => (string) $resource->get('context_key'),
            ];
        }
        return $rows;
    }
}
