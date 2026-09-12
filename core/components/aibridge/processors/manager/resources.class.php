<?php

declare(strict_types=1);

namespace AIBridge\Processors\Manager;

use AIBridge\Manager\AdminProcessor;
use AIBridge\Manager\ResourceExplorerService;

final class ResourcesProcessor extends AdminProcessor
{
    public function process()
    {
        if ($error = $this->requirePermission()) return $error;
        $service = new ResourceExplorerService($this->modx);
        $mode = (string) $this->getProperty('mode', 'search');
        return match ($mode) {
            'tree' => $this->success('', ['items' => $service->tree((int) $this->getProperty('parent', 0), (int) $this->getProperty('limit', 100), (int) $this->getProperty('depth', 2))]),
            'get' => $this->respond($service->get((int) $this->getProperty('id', 0))),
            'contract' => $this->respond($service->contract((int) $this->getProperty('id', 0))),
            'qa' => $this->respond($service->qa((int) $this->getProperty('id', 0), $this->arrayProperty('candidate'))),
            'fingerprint_diff' => $this->success('', ['diff' => $service->fingerprintDiff((int) $this->getProperty('from_id', 0), (int) $this->getProperty('to_id', 0))]),
            default => $this->success('', ['items' => $service->search((string) $this->getProperty('query', ''), $this->nullableInt('template_id'), (string) $this->getProperty('tv_name', ''), (string) $this->getProperty('tv_value', ''), (int) $this->getProperty('limit', 100))]),
        };
    }

    private function respond(?array $data): array
    {
        return $data === null ? $this->failure('Object not found.', ['code' => 'not_found']) : $this->success('', ['item' => $data]);
    }

    private function nullableInt(string $key): ?int
    {
        $value = (int) $this->getProperty($key, 0);
        return $value > 0 ? $value : null;
    }

    private function arrayProperty(string $key): array
    {
        $value = $this->getProperty($key, []);
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($value) ? $value : [];
    }
}
return ResourcesProcessor::class;
