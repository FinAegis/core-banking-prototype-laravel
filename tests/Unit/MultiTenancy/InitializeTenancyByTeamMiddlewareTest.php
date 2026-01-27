<?php

declare(strict_types=1);

namespace Tests\Unit\MultiTenancy;

use App\Exceptions\TenantCouldNotBeIdentifiedByTeamException;
use App\Http\Middleware\InitializeTenancyByTeam;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stancl\Tenancy\Tenancy;
use Tests\CreatesApplication;

/**
 * Unit tests for InitializeTenancyByTeam middleware.
 */
class InitializeTenancyByTeamMiddlewareTest extends BaseTestCase
{
    use CreatesApplication;
    use LazilyRefreshDatabase;

    protected InitializeTenancyByTeam $middleware;

    /**
     * Define environment setup - called before setUp().
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('permission.cache.store', 'array');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = app(InitializeTenancyByTeam::class);
    }

    protected function tearDown(): void
    {
        InitializeTenancyByTeam::$onFail = null;

        // End tenancy if still active
        $tenancy = app(Tenancy::class);
        if ($tenancy->initialized) {
            $tenancy->end();
        }

        parent::tearDown();
    }

    public function test_middleware_can_be_instantiated(): void
    {
        $this->assertInstanceOf(InitializeTenancyByTeam::class, $this->middleware);
    }

    public function test_middleware_skips_options_requests(): void
    {
        $request = Request::create('/test', 'OPTIONS');
        $response = new Response('OK');

        $result = $this->middleware->handle($request, fn () => $response);

        $this->assertEquals('OK', $result->getContent());
    }

    public function test_middleware_skips_unauthenticated_requests(): void
    {
        $request = Request::create('/test', 'GET');
        $response = new Response('OK');

        $result = $this->middleware->handle($request, fn () => $response);

        $this->assertEquals('OK', $result->getContent());
    }

    public function test_middleware_skips_user_without_team(): void
    {
        $user = User::factory()->create();
        $user->current_team_id = null;
        $user->save();

        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);
        $response = new Response('OK');

        $result = $this->middleware->handle($request, fn () => $response);

        $this->assertEquals('OK', $result->getContent());
    }

    public function test_middleware_initializes_tenancy_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        Tenant::createFromTeam($team);
        $user->switchTeam($team);
        $user->refresh();

        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);
        $response = new Response('OK');

        $tenancy = app(Tenancy::class);

        $result = $this->middleware->handle($request, function () use ($tenancy, $response) {
            // Inside the middleware, tenancy should be initialized
            $this->assertTrue($tenancy->initialized);

            return $response;
        });

        $this->assertEquals('OK', $result->getContent());
    }

    public function test_middleware_continues_when_tenant_not_found(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        // Don't create a tenant for this team

        $user->switchTeam($team);
        $user->refresh();

        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);
        $response = new Response('OK');

        // By default, should continue without tenant context
        $result = $this->middleware->handle($request, fn () => $response);

        $this->assertEquals('OK', $result->getContent());
    }

    public function test_middleware_uses_custom_on_fail_handler(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        // Don't create a tenant

        $user->switchTeam($team);
        $user->refresh();

        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $customResponse = new Response('Custom Error', 403);

        InitializeTenancyByTeam::$onFail = function (
            TenantCouldNotBeIdentifiedByTeamException $e,
            Request $request
        ) use ($customResponse) {
            return $customResponse;
        };

        $result = $this->middleware->handle($request, fn () => new Response('OK'));

        $this->assertEquals('Custom Error', $result->getContent());
        $this->assertEquals(403, $result->getStatusCode());
    }

    public function test_middleware_terminate_ends_tenancy(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        Tenant::createFromTeam($team);
        $user->switchTeam($team);
        $user->refresh();

        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);
        $response = new Response('OK');

        $tenancy = app(Tenancy::class);

        $this->middleware->handle($request, function () use ($tenancy, $response) {
            $this->assertTrue($tenancy->initialized);

            return $response;
        });

        // Call terminate
        $this->middleware->terminate($request, $response);

        // Tenancy should be ended
        $this->assertFalse($tenancy->initialized);
    }

    public function test_middleware_is_registered_with_correct_alias(): void
    {
        $router = app('router');
        $aliases = $router->getMiddleware();

        $this->assertArrayHasKey('tenant', $aliases);
        $this->assertEquals(InitializeTenancyByTeam::class, $aliases['tenant']);
    }
}
