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
use ReflectionClass;
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
        // Ensure rate limiter uses array cache
        $app['config']->set('cache.limiter', 'array');
        // Disable Redis for tests
        $app['config']->set('database.redis.client', 'null');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = app(InitializeTenancyByTeam::class);
    }

    protected function tearDown(): void
    {
        InitializeTenancyByTeam::$onFail = null;
        InitializeTenancyByTeam::$allowWithoutTenant = false;
        InitializeTenancyByTeam::$rateLimitAttempts = 60;

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

    // ========================================
    // Security Tests - Team Membership
    // ========================================

    public function test_middleware_blocks_user_not_belonging_to_team(): void
    {
        // Create two users with their own teams
        $user1 = User::factory()->create();
        $team1 = Team::factory()->create(['user_id' => $user1->id]);

        $user2 = User::factory()->create();
        $team2 = Team::factory()->create(['user_id' => $user2->id]);
        Tenant::createFromTeam($team2);

        // Manually set user1's current_team_id to team2 (simulating attack)
        $user1->current_team_id = $team2->id;
        $user1->save();

        $this->actingAs($user1);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user1);
        $request->headers->set('Accept', 'application/json');

        $result = $this->middleware->handle($request, fn () => new Response('OK'));

        // Should be blocked with 403
        $this->assertEquals(403, $result->getStatusCode());
        $this->assertStringContainsString('unauthorized_team_access', $result->getContent() ?: '');
    }

    public function test_middleware_allows_team_owner(): void
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

        $result = $this->middleware->handle($request, fn () => $response);

        $this->assertEquals('OK', $result->getContent());
    }

    public function test_middleware_allows_team_member(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        Tenant::createFromTeam($team);

        $member = User::factory()->create();
        $team->users()->attach($member, ['role' => 'member']);
        $member->switchTeam($team);
        $member->refresh();

        $this->actingAs($member);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $member);
        $response = new Response('OK');

        $result = $this->middleware->handle($request, fn () => $response);

        $this->assertEquals('OK', $result->getContent());
    }

    // ========================================
    // Security Tests - Rate Limiting
    // ========================================

    public function test_middleware_rate_limits_tenant_lookups(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        Tenant::createFromTeam($team);
        $user->switchTeam($team);
        $user->refresh();

        $this->actingAs($user);

        // Set low rate limit for testing
        InitializeTenancyByTeam::$rateLimitAttempts = 2;

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);
        $request->headers->set('Accept', 'application/json');

        // First two requests should work
        for ($i = 0; $i < 2; $i++) {
            $tenancy = app(Tenancy::class);
            if ($tenancy->initialized) {
                $tenancy->end();
            }

            $result = $this->middleware->handle($request, fn () => new Response('OK'));
            $this->assertEquals(200, $result->getStatusCode(), "Request {$i} should succeed");
        }

        // Third request should be rate limited
        $tenancy = app(Tenancy::class);
        if ($tenancy->initialized) {
            $tenancy->end();
        }

        $result = $this->middleware->handle($request, fn () => new Response('OK'));
        $this->assertEquals(429, $result->getStatusCode());
        $this->assertStringContainsString('rate_limit_exceeded', $result->getContent() ?: '');
    }

    // ========================================
    // Security Tests - Tenant Required
    // ========================================

    public function test_middleware_returns_403_when_tenant_not_found_and_not_allowed(): void
    {
        InitializeTenancyByTeam::$allowWithoutTenant = false;

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        // No tenant created for this team

        $user->switchTeam($team);
        $user->refresh();

        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);
        $request->headers->set('Accept', 'application/json');

        $result = $this->middleware->handle($request, fn () => new Response('OK'));

        $this->assertEquals(403, $result->getStatusCode());
        $this->assertStringContainsString('tenant_context_required', $result->getContent() ?: '');
    }

    public function test_middleware_allows_request_when_tenant_not_found_and_allowed(): void
    {
        InitializeTenancyByTeam::$allowWithoutTenant = true;

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        // No tenant created for this team

        $user->switchTeam($team);
        $user->refresh();

        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $result = $this->middleware->handle($request, fn () => new Response('OK'));

        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals('OK', $result->getContent());
    }

    // ========================================
    // Configuration Tests
    // ========================================

    public function test_allow_without_tenant_defaults_to_false(): void
    {
        // Reset to ensure default
        $middleware = app(InitializeTenancyByTeam::class);

        $reflection = new ReflectionClass(InitializeTenancyByTeam::class);
        $property = $reflection->getProperty('allowWithoutTenant');

        // Check default is false (secure by default)
        $defaultValue = $property->getDefaultValue();
        $this->assertFalse($defaultValue);
    }

    public function test_rate_limit_attempts_default_value(): void
    {
        $reflection = new ReflectionClass(InitializeTenancyByTeam::class);
        $property = $reflection->getProperty('rateLimitAttempts');

        $defaultValue = $property->getDefaultValue();
        $this->assertEquals(60, $defaultValue);
    }
}
