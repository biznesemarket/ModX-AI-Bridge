<?php

declare(strict_types=1);

namespace AIBridge\Manager;

use MODX\Revolution\Processors\Processor;

abstract class AdminProcessor extends Processor
{
    protected function requirePermission(): ?array
    {
        if (!$this->modx->user || !$this->modx->user->isAuthenticated('mgr')) {
            return $this->failure('Manager authentication is required.', ['code' => 'manager_auth_required']);
        }
        if (!$this->modx->hasPermission('aibridge_manage')) {
            return $this->failure('AIBridge Manager permission is required.', ['code' => 'manager_permission_required']);
        }
        return null;
    }

    protected function console(): OperationsConsoleService
    {
        return new OperationsConsoleService($this->modx);
    }
}
