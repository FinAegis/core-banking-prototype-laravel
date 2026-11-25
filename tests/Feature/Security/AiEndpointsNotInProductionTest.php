<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

/*
| The api/ai/* endpoints only ever return template answers: the chat agents
| fall back to canned text, the MCP tool runner is a demo that "authorizes"
| transfers it never makes, and the query endpoints describe sample data.
| Neither the Zelta app nor the website calls them. In production they now
| answer 404 for everybody, and AI demo mode defaults to off there.
*/

beforeEach(function (): void {
    Cache::flush();
    config(['cache.default' => 'array']);
});

/**
 * @return list<array{0: string, 1: string}>
 */
function aiRoutes(): array
{
    $routes = [];
    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/ai/')) {
            continue;
        }
        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $routes[] = [$method, '/' . str_replace(['{conversationId}', '{tool}'], ['conv-1', 'get_account_balance'], $route->uri())];
        }
    }

    return $routes;
}

it('finds the api/ai routes this gate is meant to cover', function (): void {
    expect(count(aiRoutes()))->toBeGreaterThanOrEqual(10);
});

it('answers 404 on every api/ai route in production, even for a signed-in user', function (): void {
    app()->detectEnvironment(fn () => 'production');
    Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);

    foreach (aiRoutes() as [$method, $uri]) {
        $response = $this->json($method, $uri, ['message' => 'hello', 'parameters' => ['account_uuid' => 'x'], 'query' => 'x']);
        expect($response->status())->toBe(404, "{$method} {$uri} should be hidden in production");
    }
});

it('answers 404 on every api/ai route in production for a guest too', function (): void {
    app()->detectEnvironment(fn () => 'production');

    foreach (aiRoutes() as [$method, $uri]) {
        expect($this->json($method, $uri, [])->status())->toBe(404, "{$method} {$uri} should be hidden in production");
    }
});

it('keeps the AI chat reachable outside production', function (string $env): void {
    app()->detectEnvironment(fn () => $env);
    Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);

    expect($this->postJson('/api/ai/chat', ['message' => 'What is my balance?'])->status())->not->toBe(404);
})->with(['demo', 'local']);

it('defaults AI demo mode to off in production and on elsewhere', function (string $env, bool $expected): void {
    $saved = [getenv('APP_ENV'), getenv('AI_DEMO_MODE'), $_SERVER['APP_ENV'] ?? null, $_ENV['APP_ENV'] ?? null, $_SERVER['AI_DEMO_MODE'] ?? null, $_ENV['AI_DEMO_MODE'] ?? null];
    putenv('APP_ENV=' . $env);
    putenv('AI_DEMO_MODE');
    $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = $env;
    unset($_SERVER['AI_DEMO_MODE'], $_ENV['AI_DEMO_MODE']);

    try {
        /** @var array<string, mixed> $config */
        $config = require base_path('config/ai.php');
    } finally {
        $saved[0] === false ? putenv('APP_ENV') : putenv('APP_ENV=' . $saved[0]);
        if ($saved[1] !== false) {
            putenv('AI_DEMO_MODE=' . $saved[1]);
        }
        [$_SERVER['APP_ENV'], $_ENV['APP_ENV']] = [$saved[2], $saved[3]];
        if ($saved[4] !== null) {
            $_SERVER['AI_DEMO_MODE'] = $saved[4];
        }
        if ($saved[5] !== null) {
            $_ENV['AI_DEMO_MODE'] = $saved[5];
        }
    }

    expect($config['demo_mode'])->toBe($expected);
})->with([
    'production' => ['production', false],
    'demo'       => ['demo', true],
    'local'      => ['local', true],
]);
