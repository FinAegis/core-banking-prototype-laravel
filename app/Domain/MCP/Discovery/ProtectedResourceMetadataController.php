<?php

declare(strict_types=1);

namespace App\Domain\MCP\Discovery;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProtectedResourceMetadataController
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'resource'                 => (string) config('mcp.resource_uri'),
            'authorization_servers'    => [(string) config('mcp.authorization_server')],
            'scopes_supported'         => array_keys((array) config('mcp.scopes', [])),
            'bearer_methods_supported' => ['header'],
            'resource_documentation'   => (string) config('mcp.authorization_server') . '/developers/mcp-tools',
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
