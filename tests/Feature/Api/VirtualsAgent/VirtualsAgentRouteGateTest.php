<?php

declare(strict_types=1);

use App\Domain\VirtualsAgent\Enums\AgentStatus;
use App\Domain\VirtualsAgent\Models\VirtualsAgentProfile;
use App\Infrastructure\Domain\ModuleRouteLoader;
use App\Models\User;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| VirtualsAgent route gate
|--------------------------------------------------------------------------
|
| /api/v1/virtuals-agents is served only while the VirtualsAgent module is
| enabled (not in MODULES_DISABLED) AND virtuals-agent.enabled
| (VIRTUALS_AGENT_ENABLED) is on. The flag is off by default under
| APP_ENV=production and on for local, testing and demo.
|
*/

/**
 * Evaluate config/virtuals-agent.php under the given env values, then restore
 * the process env.
 *
 * @param  array<string, string|null>  $env
 * @return array<string, mixed>
 */
function vaConfigUnderEnv(array $env): array
{
    $env = array_merge(['APP_ENV' => null, 'VIRTUALS_AGENT_ENABLED' => null], $env);
    $saved = [];

    foreach ($env as $key => $value) {
        $saved[$key] = [
            'has_server' => array_key_exists($key, $_SERVER),
            'server'     => $_SERVER[$key] ?? null,
            'has_env'    => array_key_exists($key, $_ENV),
            'env'        => $_ENV[$key] ?? null,
            'getenv'     => getenv($key),
        ];

        if ($value === null) {
            unset($_SERVER[$key], $_ENV[$key]);
            putenv($key);
        } else {
            $_SERVER[$key] = $value;
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    try {
        /** @var array<string, mixed> $config */
        $config = require base_path('config/virtuals-agent.php');

        return $config;
    } finally {
        foreach ($saved as $key => $previous) {
            if ($previous['has_server']) {
                $_SERVER[$key] = $previous['server'];
            } else {
                unset($_SERVER[$key]);
            }
            if ($previous['has_env']) {
                $_ENV[$key] = $previous['env'];
            } else {
                unset($_ENV[$key]);
            }
            if ($previous['getenv'] === false) {
                putenv($key);
            } else {
                putenv("{$key}={$previous['getenv']}");
            }
        }
    }
}

/**
 * Rebuild the API routes from the module route loader with only the
 * VirtualsAgent domain eligible, so the MODULES_DISABLED decision for it is
 * exercised without re-registering every other domain's routes.
 *
 * @param  array<int, string>  $alsoDisabled
 */
function vaReloadModuleRoutes(array $alsoDisabled): void
{
    $otherDomains = array_values(array_filter(
        array_map('basename', File::directories(base_path('app/Domain'))),
        fn (string $domain): bool => $domain !== 'VirtualsAgent',
    ));

    config(['modules.disabled' => array_merge($otherDomains, $alsoDisabled)]);
    Cache::forget('domain_manager_disabled');

    app('router')->setRoutes(new RouteCollection());
    Route::prefix('api')->middleware('api')->group(function (): void {
        app(ModuleRouteLoader::class)->loadRoutes();
    });
    Route::getRoutes()->refreshNameLookups();
}

describe('VIRTUALS_AGENT_ENABLED gate', function (): void {
    it('answers 404 on every virtuals-agents route while the flag is off', function (string $method, string $uri): void {
        config(['virtuals-agent.enabled' => false]);
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        // A real agent of the caller, so a 404 can only come from the gate.
        $profile = VirtualsAgentProfile::create([
            'virtuals_agent_id' => 'gate-agent',
            'employer_user_id'  => $user->id,
            'agent_name'        => 'Gate Bot',
            'status'            => AgentStatus::ACTIVE,
            'chain'             => 'base',
        ]);

        $this->json($method, str_replace('{id}', (string) $profile->id, $uri), [])->assertNotFound();
    })->with([
        'index'        => ['GET', '/api/v1/virtuals-agents'],
        'onboard'      => ['POST', '/api/v1/virtuals-agents/onboard'],
        'agdp'         => ['GET', '/api/v1/virtuals-agents/agdp'],
        'show'         => ['GET', '/api/v1/virtuals-agents/{id}'],
        'suspend'      => ['PUT', '/api/v1/virtuals-agents/{id}/suspend'],
        'activate'     => ['PUT', '/api/v1/virtuals-agents/{id}/activate'],
        'transactions' => ['GET', '/api/v1/virtuals-agents/{id}/transactions'],
    ]);

    it('serves the routes while the flag is on', function (): void {
        config(['virtuals-agent.enabled' => true]);
        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);

        $this->getJson('/api/v1/virtuals-agents')
            ->assertOk()
            ->assertJsonPath('success', true);
    });
});

describe('MODULES_DISABLED gate', function (): void {
    it('does not load the routes when VirtualsAgent is in MODULES_DISABLED, even with the flag on', function (): void {
        config(['virtuals-agent.enabled' => true]);
        vaReloadModuleRoutes(['VirtualsAgent']);
        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);

        expect(Route::has('api.virtuals-agents.index'))->toBeFalse();
        $this->getJson('/api/v1/virtuals-agents')->assertNotFound();
    });

    it('loads and serves the routes when the module is enabled and the flag is on', function (): void {
        config(['virtuals-agent.enabled' => true]);
        vaReloadModuleRoutes([]);
        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);

        expect(Route::has('api.virtuals-agents.index'))->toBeTrue();
        $this->getJson('/api/v1/virtuals-agents')->assertOk();
    });
});

describe('VIRTUALS_AGENT_ENABLED default', function (): void {
    it('is off under APP_ENV=production, staging or unset', function (?string $appEnv): void {
        expect(vaConfigUnderEnv(['APP_ENV' => $appEnv])['enabled'])->toBeFalse();
    })->with([
        'production'    => ['production'],
        'staging'       => ['staging'],
        'APP_ENV unset' => [null],
    ]);

    it('is on for local, testing and demo', function (string $appEnv): void {
        expect(vaConfigUnderEnv(['APP_ENV' => $appEnv])['enabled'])->toBeTrue();
    })->with(['local', 'testing', 'demo']);

    it('lets an explicit env value win', function (): void {
        expect(vaConfigUnderEnv(['APP_ENV' => 'production', 'VIRTUALS_AGENT_ENABLED' => 'true'])['enabled'])->toBeTrue()
            ->and(vaConfigUnderEnv(['APP_ENV' => 'demo', 'VIRTUALS_AGENT_ENABLED' => 'false'])['enabled'])->toBeFalse();
    });
});
