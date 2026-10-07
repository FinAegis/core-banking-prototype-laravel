<?php

declare(strict_types=1);

namespace App\Domain\MCP\Server;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global MCP switch (config('mcp.enabled') / MCP_ENABLED).
 *
 * Applied ahead of the OAuth guard on the /mcp transport route so that a
 * disabled server answers 503 to every caller, authenticated or not. The
 * per-tool MCP_TOOL_* flags still apply when the server is enabled.
 */
final class EnsureMcpEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('mcp.enabled', true)) {
            return new JsonResponse([
                'error'             => 'mcp_disabled',
                'error_description' => 'The MCP server is currently unavailable.',
            ], 503);
        }

        return $next($request);
    }
}
