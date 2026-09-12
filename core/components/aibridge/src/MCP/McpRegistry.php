<?php
declare(strict_types=1);
namespace AIBridge\MCP;

final class McpRegistry
{
    private array $tools=[]; private array $resources=[]; private array $prompts=[];
    public function tool(string $name, string $description, array $inputSchema, callable $handler, string $operation=''): void { $this->tools[$name]=compact('name','description','inputSchema','handler','operation'); }
    public function resource(string $uri, string $name, string $description, string $mimeType, callable $handler, string $operation=''): void { $this->resources[$uri]=compact('uri','name','description','mimeType','handler','operation'); }
    public function prompt(string $name, string $description, array $arguments, callable $handler): void { $this->prompts[$name]=compact('name','description','arguments','handler'); }
    public function tools(): array { return array_map(fn($v)=>['name'=>$v['name'],'description'=>$v['description'],'inputSchema'=>$v['inputSchema']],array_values($this->tools)); }
    public function resources(): array { return array_map(fn($v)=>['uri'=>$v['uri'],'name'=>$v['name'],'description'=>$v['description'],'mimeType'=>$v['mimeType']],array_values($this->resources)); }
    public function prompts(): array { return array_map(fn($v)=>['name'=>$v['name'],'description'=>$v['description'],'arguments'=>$v['arguments']],array_values($this->prompts)); }
    public function getTool(string $name): ?array { return $this->tools[$name]??null; }
    public function getResource(string $uri): ?array { return $this->resources[$uri]??null; }
    public function getPrompt(string $name): ?array { return $this->prompts[$name]??null; }
}
