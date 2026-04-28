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
