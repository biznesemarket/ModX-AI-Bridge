<?php

declare(strict_types=1);

namespace AIBridge\Controllers;

use AIBridge\Application\Application;

final class ApiController
{
    public function __construct(private readonly Application $application)
    {
    }

    public function health(): array
    {
        return $this->application->health();
    }

    public function siteSchema(array $input = []): array
    {
        return $this->application->discoverSite($input);
    }

    public function contentContract(array $siteSchema, ?int $templateId = null): array
    {
        return $this->application->buildContentContract($siteSchema, $templateId);
    }

    public function validateContent(array $content, array $contract): array
    {
        return $this->application->validateContent($content, $contract);
    }

    public function resourceCreate(array $input, array $principal, array $request = []): array
    { return $this->application->resourceCreate($input, $principal, $request); }

    public function resourceUpdate(array $input, array $principal, array $request = []): array
    { return $this->application->resourceUpdate($input, $principal, $request); }

    public function resourceDelete(array $input, array $principal, array $request = []): array
    { return $this->application->resourceDelete($input, $principal, $request); }

    public function resourcePreview(array $input, array $principal, array $request = []): array
    { return $this->application->resourcePreview($input, $principal, $request); }

    public function resourcePublish(array $input, array $principal, array $request = []): array
    { return $this->application->resourcePublish($input, $principal, $request); }
}
