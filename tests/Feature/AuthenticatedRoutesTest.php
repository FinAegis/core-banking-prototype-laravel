<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthenticatedRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Test that all routes used in views actually exist
     */
    public function test_all_routes_in_views_exist(): void
    {
        // Routes that should exist for authenticated users
        $routes = [
            'dashboard',
            'wallet.index',
            'wallet.transactions',
            'wallet.deposit',
            'wallet.withdraw',
            'wallet.transfer',
            'wallet.convert',
            'wallet.bank-allocation',
            'wallet.voting',
            'wallet.blockchain.index',
            'exchange.index',
            'lending.index',
            'liquidity.index',
            'api-keys.index',
            'transactions.status',
            'fund-flow.index',
            'profile.show',
            'gcu',
            'gcu.voting.index',
        ];

        foreach ($routes as $routeName) {
            try {
                $url = route($routeName);
                $this->assertNotEmpty($url, "Route {$routeName} generated empty URL");
            } catch (\Exception $e) {
                $this->fail("Route '{$routeName}' is not defined but is used in views. Error: " . $e->getMessage());
            }
        }
    }

    /**
     * Test that authenticated pages don't show route errors
     */
    public function test_authenticated_pages_without_route_errors(): void
    {
        // Pages that should load without route errors
        $pages = [
            '/dashboard',
            '/wallet',
            '/profile',
        ];

        foreach ($pages as $page) {
            $response = $this->actingAs($this->user)->get($page);
            
            // Check that we don't get a 500 error
            $this->assertNotEquals(500, $response->status(), "Page {$page} returned 500 error");
            
            // Check for route errors in content if status is 200
            if ($response->status() === 200) {
                $response->assertDontSee('Route [', false);
                $response->assertDontSee('not defined', false);
            }
        }
    }

    /**
     * Test navigation menu renders without errors
     */
    public function test_navigation_menu_renders_without_errors(): void
    {
        // Test rendering the navigation menu view directly
        $view = $this->actingAs($this->user)
                     ->view('navigation-menu');
        
        // The view should render without throwing exceptions
        $this->assertNotNull($view);
    }

    /**
     * Test that all main authenticated routes are accessible
     */
    public function test_main_authenticated_routes_accessible(): void
    {
        $routes = [
            '/dashboard' => [200],
            '/wallet' => [200],
            '/exchange' => [200, 302], // May redirect to login or show page
            '/lending' => [200, 302],
            '/liquidity' => [200, 302],
            '/api-keys' => [200, 302, 403], // May be forbidden for some users
        ];

        foreach ($routes as $url => $expectedStatuses) {
            $response = $this->actingAs($this->user)->get($url);
            
            $this->assertContains(
                $response->status(), 
                $expectedStatuses,
                "Unexpected status {$response->status()} for {$url}. Expected one of: " . implode(', ', $expectedStatuses)
            );
            
            // If we got a 200, check for errors
            if ($response->status() === 200) {
                $response->assertDontSee('Route [', false);
                $response->assertDontSee('not defined', false);
                $response->assertDontSee('Exception', false);
            }
        }
    }
}