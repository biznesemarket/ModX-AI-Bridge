<?php

declare(strict_types=1);
namespace AIBridge\Processors\Manager;
use AIBridge\Manager\AdminProcessor;
final class OverviewProcessor extends AdminProcessor
{
    public function process() {
        if ($error = $this->requirePermission()) return $error;
        $c = $this->console();
        return $this->success('', ['overview' => $c->overview(), 'profiles' => $c->profiles(50), 'tokens' => $c->tokens(50), 'policies' => $c->policies(50), 'jobs' => $c->jobs(50), 'audit' => $c->audit(50), 'fingerprints' => $c->fingerprints(50)]);
    }
}
return OverviewProcessor::class;
