<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

/*
| POST /api/demo/ai-chat answered an outsider on zelta.app. Every api/demo/*
| route is now 404 when APP_ENV is production and stays available elsewhere
| (the FinAegis demo runs APP_ENV=demo).
*/

beforeEach(function (): void {
    Cache::flush();
    config(['cache.default' => 'array']);
});

it('has only the demo AI chat under api/demo, so the gate below covers every demo route', function (): void {
    $demo = [];
    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (str_starts_with($route->uri(), 'api/demo/')) {
            $demo[] = $route->uri();
        }
    }

    expect($demo)->toBe(['api/demo/ai-chat']);
});

it('answers 404 on every api/demo route in production', function (): void {
    app()->detectEnvironment(fn () => 'production');

    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/demo/')) {
            continue;
        }
        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $this->json($method, '/' . $route->uri(), ['message' => 'hello'])->assertNotFound();
        }
    }
});

it('keeps the demo AI chat working in the demo environment', function (string $env): void {
    app()->detectEnvironment(fn () => $env);

    $this->postJson('/api/demo/ai-chat', ['message' => 'What is my balance?'])
        ->assertOk();
})->with(['demo', 'local']);

it('keeps the Virtuals agents API closed in production with the default config', function (): void {
    // config/virtuals-agent.php under APP_ENV=production with VIRTUALS_AGENT_ENABLED unset.
    $saved = [getenv('APP_ENV'), getenv('VIRTUALS_AGENT_ENABLED')];
    putenv('APP_ENV=production');
    putenv('VIRTUALS_AGENT_ENABLED');
    $previousServer = [$_SERVER['APP_ENV'] ?? null, $_ENV['APP_ENV'] ?? null, $_SERVER['VIRTUALS_AGENT_ENABLED'] ?? null, $_ENV['VIRTUALS_AGENT_ENABLED'] ?? null];
    $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'production';
    unset($_SERVER['VIRTUALS_AGENT_ENABLED'], $_ENV['VIRTUALS_AGENT_ENABLED']);

    try {
        /** @var array<string, mixed> $config */
        $config = require base_path('config/virtuals-agent.php');
    } finally {
        $saved[0] === false ? putenv('APP_ENV') : putenv('APP_ENV=' . $saved[0]);
        if ($saved[1] !== false) {
            putenv('VIRTUALS_AGENT_ENABLED=' . $saved[1]);
        }
        [$_SERVER['APP_ENV'], $_ENV['APP_ENV']] = [$previousServer[0], $previousServer[1]];
        if ($previousServer[2] !== null) {
            $_SERVER['VIRTUALS_AGENT_ENABLED'] = $previousServer[2];
        }
        if ($previousServer[3] !== null) {
            $_ENV['VIRTUALS_AGENT_ENABLED'] = $previousServer[3];
        }
    }

    expect($config['enabled'])->toBeFalse();

    config(['virtuals-agent' => $config]);
    app()->detectEnvironment(fn () => 'production');

    // A guest is refused by auth:sanctum first (it sorts before the feature
    // flag); a signed-in user gets 404 from the flag.
    $this->getJson('/api/v1/virtuals-agents')->assertUnauthorized();

    Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);
    $this->getJson('/api/v1/virtuals-agents')->assertNotFound();
    $this->postJson('/api/v1/virtuals-agents/onboard', [])->assertNotFound();
});
