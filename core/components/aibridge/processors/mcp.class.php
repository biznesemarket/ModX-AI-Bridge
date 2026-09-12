<?php
declare(strict_types=1);
namespace AIBridge\Processors;
use MODX\Revolution\Processors\Processor;
use AIBridge\MCP\McpServer;
final class McpProcessor extends Processor
{
    public function process() {
        $server=McpServer::fromModx($this->modx);
        $request=$this->getProperties();
        $principal=['scopes'=>array_filter(array_map('trim',explode(',',(string)$this->getProperty('scopes',''))))];
        return $this->success('', $server->handle($request,$principal));
    }
}
return McpProcessor::class;
