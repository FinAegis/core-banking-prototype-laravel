<?php

declare(strict_types=1);

use Illuminate\Foundation\Bootstrap\LoadConfiguration;

it('responds on the mcp subdomain healthz', function () {
    // Override APP_URL after LoadConfiguration so SetRequestForConsole builds the
    // console request with mcp.zelta.app, activating the MCP route branch in bootstrap/app.php.
    $app = require base_path('bootstrap/app.php');

    $app->afterBootstrapping(
        LoadConfiguration::class,
        static function ($app): void {
            $app['config']->set('app.url', 'http://mcp.zelta.app');
        }
    );

    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    $this->app = $app;

    // Run migrations on the fresh in-memory connection before any HTTP request
    // so middleware like TracingMiddleware can access required tables.
    $this->artisan('migrate', ['--force' => true]);

    $response = $this->withServerVariables(['HTTP_HOST' => config('mcp.host')])
        ->getJson('/healthz');

    $response->assertStatus(200)
        ->assertJson(['ok' => true, 'service' => 'mcp']);
});

it('does not expose mcp routes on the main domain', function () {
    // Default test boot uses APP_URL=http://localhost, so MCP routes land at api/healthz
    // via ModuleRouteLoader. A plain GET /healthz is not registered → 404.
    $response = $this->withServerVariables(['HTTP_HOST' => 'zelta.app'])
        ->getJson('/healthz');

    $response->assertStatus(404);
});
