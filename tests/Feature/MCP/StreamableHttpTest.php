<?php

declare(strict_types=1);

namespace Tests\Feature\MCP;

use Illuminate\Foundation\Bootstrap\LoadConfiguration;

// Feature coverage for the MCP streamable-HTTP transport at mcp.zelta.app/mcp.
// The MCP subdomain branch in bootstrap/app.php is selected based on
// request()->getHost() at boot time, so each test rebuilds the application
// with app.url=http://mcp.zelta.app to activate the MCP route group -- same
// pattern used by SubdomainRoutingTest.
it('returns 401 with WWW-Authenticate when no bearer token on POST /mcp', function () {
    $app = require base_path('bootstrap/app.php');

    $app->afterBootstrapping(
        LoadConfiguration::class,
        static function ($app): void {
            $app['config']->set('app.url', 'http://mcp.zelta.app');
        }
    );

    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    $this->app = $app;

    $this->artisan('migrate', ['--force' => true]);

    $response = $this->withServerVariables(['HTTP_HOST' => (string) config('mcp.host')])
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']);

    expect($response->status())->toBe(401);
    expect((string) $response->headers->get('WWW-Authenticate'))->toContain('Bearer');
    expect((string) $response->headers->get('WWW-Authenticate'))->toContain('resource_metadata=');
});

it('returns 406 or 401 when GET /mcp lacks Accept: text/event-stream', function () {
    // Even unauthenticated, the 401 may win — but we still want to verify the
    // 406 path triggers when Accept is wrong AND auth would otherwise pass.
    // Both 401 (auth missing/invalid) and 406 (wrong Accept) are valid here.
    $app = require base_path('bootstrap/app.php');

    $app->afterBootstrapping(
        LoadConfiguration::class,
        static function ($app): void {
            $app['config']->set('app.url', 'http://mcp.zelta.app');
        }
    );

    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    $this->app = $app;

    $this->artisan('migrate', ['--force' => true]);

    // No Authorization header — McpOAuthGuard short-circuits to 401 before
    // ever hitting the Accept-header check. We accept both 401 and 406 here
    // (the SSE 406 path is verified in unit-level coverage; this confirms the
    // route + middleware wiring on the MCP subdomain).
    $response = $this->withServerVariables(['HTTP_HOST' => (string) config('mcp.host')])
        ->withHeaders(['Accept' => 'application/json'])
        ->get('/mcp');

    expect($response->status())->toBeIn([401, 406]);
});

it('returns 405 with Allow: POST when GET /mcp is called and SSE is disabled (default)', function () {
    // SSE defaults to disabled because a long-lived SSE response inside PHP-FPM
    // pins a worker for the entire connection lifetime. POST /mcp remains the
    // primary transport; the MCP spec lets servers return 405 on GET.
    config(['mcp.sse.enabled' => false]);
    $controller = app(\App\Domain\MCP\Server\StreamableHttpController::class);

    $request = \Illuminate\Http\Request::create('/mcp', 'GET');
    $request->headers->set('Accept', 'text/event-stream');

    $response = $controller->handle($request);

    expect($response->getStatusCode())->toBe(405);
    expect((string) $response->headers->get('Allow'))->toBe('POST');
    expect((string) $response->getContent())->toContain('not enabled');
});

it('returns 406 directly from the controller when GET lacks Accept: text/event-stream and SSE is enabled', function () {
    // Bypass OAuth entirely: drive the controller directly so we can prove the
    // 406 branch fires. The middleware-level coverage above asserts the route
    // is wired; this asserts the controller's content-negotiation logic.
    config(['mcp.sse.enabled' => true]);
    $controller = app(\App\Domain\MCP\Server\StreamableHttpController::class);

    $request = \Illuminate\Http\Request::create('/mcp', 'GET');
    $request->headers->set('Accept', 'application/json');

    $response = $controller->handle($request);

    expect($response->getStatusCode())->toBe(406);
    expect((string) $response->getContent())->toContain('event-stream');
});

/**
 * Build a POST /mcp request carrying a JSON-RPC envelope, optionally with
 * extra headers, suitable for driving StreamableHttpController directly
 * (bypassing the OAuth middleware — same pattern as the GET tests above).
 *
 * @param array<string, mixed>  $envelope
 * @param array<string, string> $headers
 */
function mcpPostRequest(array $envelope, array $headers = []): \Illuminate\Http\Request
{
    $request = \Illuminate\Http\Request::create(
        '/mcp',
        'POST',
        server: ['CONTENT_TYPE' => 'application/json'],
        content: (string) json_encode($envelope),
    );

    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    return $request;
}

it('returns 202 with an empty body for a JSON-RPC notification (no id) on POST /mcp', function () {
    $controller = app(\App\Domain\MCP\Server\StreamableHttpController::class);

    // notifications/initialized is what every conformant client sends right
    // after the initialize handshake. No `id` => notification => no response
    // envelope, just 202 Accepted.
    $response = $controller->handle(mcpPostRequest([
        'jsonrpc' => '2.0',
        'method'  => 'notifications/initialized',
    ]));

    expect($response->getStatusCode())->toBe(202);
    expect((string) $response->getContent())->toBe('');
});

it('returns 400 naming supported versions for an unsupported MCP-Protocol-Version header', function () {
    $controller = app(\App\Domain\MCP\Server\StreamableHttpController::class);

    $response = $controller->handle(mcpPostRequest(
        ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'],
        ['MCP-Protocol-Version' => '1999-01-01'],
    ));

    expect($response->getStatusCode())->toBe(400);
    $body = json_decode((string) $response->getContent(), true);
    expect($body['error'])->toBe('unsupported_protocol_version');
    expect($body['supported_versions'])->toContain((string) config('mcp.protocol_version'));
    expect($body['supported_versions'])->toContain('2025-06-18');
});

it('accepts a supported MCP-Protocol-Version header on POST /mcp', function () {
    $controller = app(\App\Domain\MCP\Server\StreamableHttpController::class);

    $response = $controller->handle(mcpPostRequest(
        ['jsonrpc' => '2.0', 'id' => 9, 'method' => 'ping'],
        ['MCP-Protocol-Version' => '2025-06-18'],
    ));

    expect($response->getStatusCode())->toBe(200);
    $body = json_decode((string) $response->getContent(), true);
    expect($body['id'])->toBe(9);
    expect($body)->toHaveKey('result');
});

it('treats a missing MCP-Protocol-Version header as the current version (backwards compatible)', function () {
    $controller = app(\App\Domain\MCP\Server\StreamableHttpController::class);

    $response = $controller->handle(mcpPostRequest(['jsonrpc' => '2.0', 'id' => 10, 'method' => 'ping']));

    expect($response->getStatusCode())->toBe(200);
});

it('ignores the MCP-Protocol-Version header on initialize (negotiation happens in params)', function () {
    $controller = app(\App\Domain\MCP\Server\StreamableHttpController::class);

    $response = $controller->handle(mcpPostRequest(
        [
            'jsonrpc' => '2.0',
            'id'      => 11,
            'method'  => 'initialize',
            'params'  => ['protocolVersion' => '2025-06-18'],
        ],
        ['MCP-Protocol-Version' => 'garbage-value'],
    ));

    expect($response->getStatusCode())->toBe(200);
    $body = json_decode((string) $response->getContent(), true);
    expect($body['result']['protocolVersion'])->toBe('2025-06-18');
});

it('opens SSE stream with right headers on GET Accept: text/event-stream when enabled', function () {
    config(['mcp.sse.enabled' => true]);
    // Don't actually consume the stream body — just verify the headers.
    $controller = app(\App\Domain\MCP\Server\StreamableHttpController::class);

    $request = \Illuminate\Http\Request::create('/mcp', 'GET');
    $request->headers->set('Accept', 'text/event-stream');

    $response = $controller->handle($request);

    expect($response->getStatusCode())->toBe(200);
    expect((string) $response->headers->get('Content-Type'))->toContain('text/event-stream');
    expect((string) $response->headers->get('Cache-Control'))->toContain('no-cache');
    expect((string) $response->headers->get('X-Accel-Buffering'))->toBe('no');
});
