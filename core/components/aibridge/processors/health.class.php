<?php

declare(strict_types=1);

namespace AIBridge\Processors;

use MODX\Revolution\Processors\Processor;

final class HealthProcessor extends Processor
{
    public function process()
    {
        return $this->success('', [
            'component' => 'modx-ai-bridge',
            'status' => 'ok',
            'version' => '0.5.0',
        ]);
    }
}

return HealthProcessor::class;
