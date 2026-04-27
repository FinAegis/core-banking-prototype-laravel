<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Health check on the MCP subdomain (no auth, no rate limit) — proves routing is wired.
// The domain constraint ensures this route only resolves on mcp.* regardless of which
// bootstrap branch loaded this file.
Route::domain(config('mcp.host'))
    ->get('/healthz', function () {
        return response()->json(['ok' => true, 'service' => 'mcp', 'protocol_version' => config('mcp.protocol_version')]);
    })->name('mcp.healthz');
