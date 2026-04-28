<?php

declare(strict_types=1);

use App\Domain\MCP\Discovery\ProtectedResourceMetadataController;
use App\Domain\MCP\Server\StreamableHttpController;
use Illuminate\Support\Facades\Route;

// Health check on the MCP subdomain (no auth, no rate limit) — proves routing is wired.
// The domain constraint ensures this route only resolves on mcp.* regardless of which
// bootstrap branch loaded this file.
Route::domain(config('mcp.host'))
    ->get('/healthz', function () {
        return response()->json(['ok' => true, 'service' => 'mcp', 'protocol_version' => config('mcp.protocol_version')]);
    })->name('mcp.healthz');

// RFC 9728 Protected Resource Metadata — unauthenticated discovery endpoint.
// Clients request this endpoint when they receive a 401 with WWW-Authenticate
// containing resource_metadata URI. The response tells them which auth server to use.
// Rate-limited per IP to prevent reconnaissance scanning.
Route::domain((string) config('mcp.host'))
    ->get('/.well-known/oauth-protected-resource', ProtectedResourceMetadataController::class)
    ->middleware(['throttle:mcp.discovery'])
    ->name('mcp.discovery.protected-resource');

// MCP streamable-HTTP transport (spec 2025-11-25).
//   POST /mcp — JSON-RPC envelope in/out (handled by JsonRpcRouter)
//   GET  /mcp — long-lived SSE stream for server→client notifications (heartbeat only for now)
// Both methods require a verified bearer token via `mcp.oauth` and are aggregate-rate-limited
// per token (per-minute + per-hour windows from config('mcp.rate_limits.aggregate')).
Route::domain((string) config('mcp.host'))
    ->match(['GET', 'POST'], '/mcp', [StreamableHttpController::class, 'handle'])
    ->middleware(['mcp.oauth', 'throttle:mcp.aggregate'])
    ->name('mcp.endpoint');
