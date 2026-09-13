<?php
declare(strict_types=1);
namespace AIBridge\Processors;
use AIBridge\Manager\AdminProcessor;
use AIBridge\MCP\McpServer;

/**
 * Manager connector bridge to the MCP server.
 *
 * This is a manager-only surface: it requires an authenticated manager with the
 * `aibridge_manage` permission. The principal and the client IP are derived from
 * the authenticated MODX session and the real transport peer; request-supplied
 * `scopes` or `_client_ip` are never trusted. Token-authenticated MCP traffic
 * uses the REST front controller (`POST /api/ai/v2/mcp`) instead.
 */
final class McpProcessor extends AdminProcessor
{
    public function process()
    {
        $denied = $this->requirePermission();
        if ($denied) return $denied;

        $server = McpServer::fromModx($this->modx);
        $request = $this->getProperties();
        $principal = [
            'type' => 'manager',
            'id' => (string) $this->modx->user->get('id'),
            'scopes' => ['*'],
            'manager_authorized' => true,
            'channel' => 'manager',
            'client_ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ];

        return $this->success('', $server->handle($request, $principal));
    }
}
return McpProcessor::class;
