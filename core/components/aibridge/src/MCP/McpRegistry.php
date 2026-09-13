<?php
declare(strict_types=1);
namespace AIBridge\MCP;

final class McpRegistry
{
    private array $tools=[]; private array $resources=[]; private array $resourceTemplates=[]; private array $prompts=[];
    public function tool(string $name, string $description, array $inputSchema, callable $handler, string $operation=''): void { $this->tools[$name]=compact('name','description','inputSchema','handler','operation'); }
    public function resource(string $uri, string $name, string $description, string $mimeType, callable $handler, string $operation=''): void { $this->resources[$uri]=compact('uri','name','description','mimeType','handler','operation'); }
    public function resourceTemplate(string $uriTemplate, string $name, string $description, string $mimeType, callable $handler, string $operation=''): void { $this->resourceTemplates[$uriTemplate]=compact('uriTemplate','name','description','mimeType','handler','operation'); }
    public function prompt(string $name, string $description, array $arguments, callable $handler): void { $this->prompts[$name]=compact('name','description','arguments','handler'); }
    public function tools(): array { return array_map(fn($v)=>['name'=>$v['name'],'description'=>$v['description'],'inputSchema'=>$v['inputSchema']],array_values($this->tools)); }
    public function resources(): array { return array_map(fn($v)=>['uri'=>$v['uri'],'name'=>$v['name'],'description'=>$v['description'],'mimeType'=>$v['mimeType']],array_values($this->resources)); }
    public function resourceTemplates(): array { return array_map(fn($v)=>['uriTemplate'=>$v['uriTemplate'],'name'=>$v['name'],'description'=>$v['description'],'mimeType'=>$v['mimeType']],array_values($this->resourceTemplates)); }
    public function prompts(): array { return array_map(fn($v)=>['name'=>$v['name'],'description'=>$v['description'],'arguments'=>$v['arguments']],array_values($this->prompts)); }
    public function getTool(string $name): ?array { return $this->tools[$name]??null; }
    public function getResource(string $uri): ?array { return $this->resources[$uri]??null; }
    public function getPrompt(string $name): ?array { return $this->prompts[$name]??null; }

    /**
     * Resolve a concrete URI against the registered resources and URI templates.
     *
     * Returns the matching entry plus any template parameters (for example
     * `id` for `modx://resource/{id}`), or null when nothing matches.
     *
     * @return array{resource:array,parameters:array<string,string>}|null
     */
    public function resolveResource(string $uri): ?array
    {
        if (isset($this->resources[$uri])) {
            return ['resource'=>$this->resources[$uri],'parameters'=>[]];
        }
        foreach ($this->resourceTemplates as $template=>$entry) {
            if (preg_match($this->templatePattern($template),$uri,$matches)!==1) {
                continue;
            }
            $parameters=[];
            foreach ($matches as $key=>$value) {
                if (is_string($key)) {
                    $parameters[$key]=(string)$value;
                }
            }
            return ['resource'=>$entry,'parameters'=>$parameters];
        }
        return null;
    }

    private function templatePattern(string $template): string
    {
        $parts=preg_split('/(\{[a-zA-Z_][a-zA-Z0-9_]*\})/',$template,-1,PREG_SPLIT_DELIM_CAPTURE);
        $pattern='';
        foreach ($parts as $part) {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/',$part,$match)===1) {
                $pattern.='(?P<'.$match[1].'>[^/]+)';
            } else {
                $pattern.=preg_quote($part,'#');
            }
        }
        return '#^'.$pattern.'$#';
    }
}
