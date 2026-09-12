<?php

declare(strict_types=1);

namespace AIBridge\Processors\Manager;

use AIBridge\Manager\AdminProcessor;
use AIBridge\Manager\ResourceOperationService;

final class ResourceOperationProcessor extends AdminProcessor
{
    public function process()
    {
        if ($error = $this->requirePermission()) return $error;
        $operation = (string) $this->getProperty('operation');
        $input = $this->getProperty('input', []);
        if (is_string($input)) {
            $input = json_decode($input, true);
        }
        if (!is_array($input)) return $this->failure('input must be an object.', ['code' => 'invalid_input']);

        $profileId = (int) $this->getProperty('profile_id', 0);
        if ($profileId < 1) {
            return $this->failure('profile_id is required.', ['code' => 'profile_required']);
        }

        $profile = $this->modx->getObject(\AIBridge\Model\Profile::class, ['id' => $profileId, 'status' => 'active']);
        if (!$profile) {
            return $this->failure('Active profile not found.', ['code' => 'profile_not_found']);
        }

        $manager = [
            'id' => $this->modx->user->get('id'),
            'profile_id' => $profileId,
            'manager_authorized' => true,
        ];

        if ($operation === 'resource.preview' && $this->getProperty('sync', '0') === '1') {
            return $this->success('', (new ResourceOperationService($this->modx))->previewNow($input));
        }

        $result = (new ResourceOperationService($this->modx))->dispatch($operation, $input, $manager);
        return ($result['success'] ?? false) ? $this->success('', $result) : $this->failure('Operation rejected.', $result['error'] ?? []);
    }
}
return ResourceOperationProcessor::class;
