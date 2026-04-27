<?php

declare(strict_types=1);

use App\Domain\MCP\Discovery\ProtectedResourceMetadataController;
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
Route::domain((string) config('mcp.host'))
    ->get('/.well-known/oauth-protected-resource', ProtectedResourceMetadataController::class)
    ->name('mcp.discovery.protected-resource');
