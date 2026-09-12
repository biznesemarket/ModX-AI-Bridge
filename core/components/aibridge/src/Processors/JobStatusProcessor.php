<?php

declare(strict_types=1);

namespace AIBridge\Processors;

use AIBridge\Queue\QueueManager;
use MODX\Revolution\Processors\Processor;

final class JobStatusProcessor extends Processor
{
    public function process()
    {
        $id = (int) $this->getProperty('id');
        if ($id < 1) return $this->failure('Job id is required.');
        $job = (new QueueManager($this->modx))->get($id);
        if (!$job) return $this->failure('Job not found.');
        return $this->success('', $job->raw());
    }
}

return JobStatusProcessor::class;
