<?php

declare(strict_types=1);

namespace AIBridge\Application;

use AIBridge\Services\SiteIntelligenceService;
use AIBridge\Services\ContentContractService;
use AIBridge\Services\ResourceReadService;
use AIBridge\Validators\ContentValidator;
use AIBridge\Configuration\ConfigFactory;
use AIBridge\Security\Authorization;
use AIBridge\Security\IpAllowlist;
use AIBridge\Security\SecurityDecisionPipeline;
use AIBridge\AI\CapabilityService;
use AIBridge\Execution\ResourceExecutionService;
use AIBridge\Queue\Job;
use AIBridge\Queue\QueueManager;
use AIBridge\MultiSite\ProfileService;
use MODX\Revolution\modX;

final class Application
{
    public function __construct(private readonly modX $modx)
    {
    }

    public function getModx(): modX { return $this->modx; }

    public function profile(int $id): array
    { $p=(new ProfileService($this->modx))->requireActive($id); return $p->toArray(); }

    public function createProfile(array $input): array
    { return (new ProfileService($this->modx))->create($input)->toArray(); }

    public function health(): array
    {
        return ['component' => 'modx-ai-bridge', 'status' => 'ok', 'version' => '0.9.3'];
    }

    public function discoverSite(array $input = []): array
    {
        return (new SiteIntelligenceService($this->modx))->discover($input);
    }

    public function buildContentContract(array $siteSchema, ?int $templateId = null): array
    {
        return (new ContentContractService())->resource($siteSchema, $templateId)->toArray();
    }

    public function securityDecision(array $request, array $principal, string $operation): array
    {
        $config = ConfigFactory::fromModx($this->modx);
        $decision = (new SecurityDecisionPipeline($config, new Authorization(), new IpAllowlist(), new \AIBridge\Services\PolicyService($config), $this->modx))->decide($request, $principal, $operation);
        return $decision->toArray();
    }

    public function aiCapabilities(): array
    {
        return (new CapabilityService(modx: $this->modx))->manifest();
    }

    public function dispatchJob(Job $job): string
    {
        return (new QueueManager($this->modx))->dispatch($job);
    }

    public function jobStatus(int $id): array
    {
        $job = (new QueueManager($this->modx))->get($id);
        if (!$job) return ['success' => false, 'error' => ['code' => 'job_not_found']];
        return ['success' => true, 'job' => $job->raw()];
    }

    public function validateContent(array $content, array $contract): array
    {
        return (new ContentValidator())->validate($content, $contract);
    }

    public function resourceCreate(array $input, array $principal, array $request = []): array
    { return (new ResourceExecutionService($this->modx))->create($input, $principal, $request); }

    public function resourceRead(int $id): array
    { return (new ResourceReadService($this->modx))->read($id); }

    public function resourceList(array $filters = []): array
    { return (new ResourceReadService($this->modx))->list($filters); }

    public function resourceUpdate(array $input, array $principal, array $request = []): array
    { return (new ResourceExecutionService($this->modx))->update($input, $principal, $request); }

    public function resourceDelete(array $input, array $principal, array $request = []): array
    { return (new ResourceExecutionService($this->modx))->delete($input, $principal, $request); }

    public function resourcePreview(array $input, array $principal, array $request = []): array
    { return (new ResourceExecutionService($this->modx))->preview($input, $principal, $request); }

    public function resourcePublish(array $input, array $principal, array $request = []): array
    { return (new ResourceExecutionService($this->modx))->publish($input, $principal, $request); }
}
