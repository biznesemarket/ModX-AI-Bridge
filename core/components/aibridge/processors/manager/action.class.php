<?php

declare(strict_types=1);
namespace AIBridge\Processors\Manager;
use AIBridge\Manager\AdminProcessor;
final class ActionProcessor extends AdminProcessor
{
    public function process() {
        if ($error = $this->requirePermission()) return $error;
        $entity = (string)$this->getProperty('entity');
        $id = (int)$this->getProperty('id');
        $status = (string)$this->getProperty('status');
        if ($id < 1 || $status === '') return $this->failure('id and status are required.');
        $console = $this->console();
        $ok = match ($entity) {
            'profile' => $console->setProfileStatus($id, $status),
            'token' => $console->setTokenStatus($id, $status),
            'policy' => $console->setPolicyStatus($id, $status),
            default => false,
        };
        return $ok ? $this->success('', ['entity' => $entity, 'id' => $id, 'status' => $status]) : $this->failure('Operation rejected or object not found.');
    }
}
return ActionProcessor::class;
