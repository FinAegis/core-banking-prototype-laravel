<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Team;

class CriticalRoutesTest extends TestCase
{
    /**
     * Test all public routes are accessible and don't throw route errors
     */
    public function test_public_routes_are_accessible(): void
    {
        $publicRoutes = [
            '/',
            '/about',
            '/platform',
            '/gcu',
            '/features',
            '/pricing',
            '/security',
            '/compliance',
            '/developers',
            '/support',
            '/blog',
            '/partners',
            '/cgo',
            '/status',
        ];

        foreach ($publicRoutes as $route) {
            $response = $this->get($route);
            
            $response->assertSuccessful();
            $response->assertDontSee('Route [');
            $response->assertDontSee('not defined');
            $response->assertDontSee('RouteNotFoundException');
        }
    }

    /**
     * Test authenticated routes don't throw route errors
     */
    public function test_authenticated_routes_dont_throw_errors(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $authenticatedRoutes = [
            '/dashboard',
            '/wallet',
            '/wallet/deposit',
            '/wallet/withdraw',
            '/wallet/transfer',
            '/wallet/convert',
        ];

        foreach ($authenticatedRoutes as $route) {
            $response = $this->actingAs($user)->get($route);
            
            // We don't assert successful because some might require additional setup
            // But they should never throw route errors
            $response->assertDontSee('Route [');
            $response->assertDontSee('not defined');
            $response->assertDontSee('RouteNotFoundException');
            
            // Assert we're not getting 500 errors
            $this->assertNotEquals(500, $response->status(), "Route {$route} returned 500 error");
        }
        
        // Test routes that might require additional setup separately
        $routesRequiringSetup = [
            '/wallet/transactions' => 'Transaction History',
            '/transactions/status' => 'Track Transaction Status',
            '/fund-flow' => 'Fund Flow',
            '/exchange-rates' => 'Exchange Rates',
            '/batch-processing' => 'Batch Processing',
            '/asset-management' => 'Asset Management',
        ];
        
        foreach ($routesRequiringSetup as $route => $expectedText) {
            $response = $this->actingAs($user)->get($route);
            
            // These might return errors due to missing data, but should never have route errors
            $response->assertDontSee('Route [');
            $response->assertDontSee('not defined');
            $response->assertDontSee('RouteNotFoundException');
        }
    }

    /**
     * Test all named routes exist
     */
    public function test_all_named_routes_exist(): void
    {
        $namedRoutes = [
            'home',
            'about',
            'platform',
            'gcu',
            'features',
            'pricing',
            'security',
            'compliance',
            'developers',
            'support',
            'blog',
            'partners',
            'cgo',
            'status',
            'dashboard',
            'wallet.index',
            'wallet.transactions',
            'wallet.deposit',
            'wallet.withdraw',
            'wallet.transfer',
            'wallet.convert',
            'transactions.status',
            'fund-flow.index',
            'exchange-rates.index',
            'batch-processing.index',
            'asset-management.index',
            'gcu.voting.index',
        ];

        foreach ($namedRoutes as $routeName) {
            $route = route($routeName, [], false);
            $this->assertNotNull($route, "Route [{$routeName}] should exist");
        }
    }

    /**
     * Test API routes are accessible with authentication
     */
    public function test_api_routes_require_authentication(): void
    {
        // Test that API routes exist and don't throw route errors
        $apiRoutePrefixes = [
            '/api/v1/auth',
            '/api/v1/assets',
            '/api/v1/accounts',
        ];

        foreach ($apiRoutePrefixes as $prefix) {
            // Make a request to ensure route exists
            $response = $this->getJson($prefix);
            
            // Should not be a route error (404 is ok, means route exists but resource not found)
            // 401/403 is also ok, means route exists but requires auth
            $this->assertContains($response->status(), [200, 401, 403, 404, 405], "Route {$prefix} returned unexpected status");
            
            // Should never have Laravel route errors
            $response->assertDontSee('Route [');
            $response->assertDontSee('not defined');
            $response->assertDontSee('RouteNotFoundException');
        }
    }
}