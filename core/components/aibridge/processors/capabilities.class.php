<?php
declare(strict_types=1);
namespace AIBridge\Processors;
use MODX\Revolution\Processors\Processor;
use AIBridge\AI\CapabilityService;
final class CapabilitiesProcessor extends Processor
{
    public function process() { return $this->success('',(new CapabilityService())->manifest()); }
}
return CapabilitiesProcessor::class;
