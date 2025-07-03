<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Route;

class RouteIntegrityTest extends TestCase
{
    /**
     * Test that all defined routes are properly named and don't have syntax errors
     */
    public function test_all_routes_are_properly_defined(): void
    {
        $routes = Route::getRoutes();
        $routeErrors = [];
        
        foreach ($routes as $route) {
            // Skip vendor routes
            if (str_contains($route->uri(), 'telescope') || 
                str_contains($route->uri(), 'horizon') ||
                str_contains($route->uri(), 'pulse') ||
                str_contains($route->uri(), '_ignition') ||
                str_contains($route->uri(), 'sanctum')) {
                continue;
            }
            
            // Check if route has a name
            $routeName = $route->getName();
            
            // Check common route patterns that should have names
            if ($routeName === null && !str_starts_with($route->uri(), 'api/')) {
                if (preg_match('/^(dashboard|wallet|transactions|accounts|fund-flow|exchange-rates)/', $route->uri())) {
                    $routeErrors[] = "Route {$route->uri()} should have a name";
                }
            }
        }
        
        $this->assertEmpty($routeErrors, "Found routes without proper names: \n" . implode("\n", $routeErrors));
    }

    /**
     * Test that navigation menu routes all exist
     */
    public function test_navigation_menu_routes_exist(): void
    {
        // Routes used in navigation menu
        $navigationRoutes = [
            'dashboard',
            'wallet.index',
            'wallet.transactions',
            'transactions.status',
            'fund-flow.index',
            'exchange-rates.index',
            'batch-processing.index',
            'asset-management.index',
            'monitoring.transactions.index',
            'wallet.deposit',
            'wallet.withdraw',
            'wallet.transfer',
            'wallet.convert',
            'gcu.voting.index',
            'wallet.voting',
            'cgo.invest',
        ];
        
        foreach ($navigationRoutes as $routeName) {
            try {
                $url = route($routeName);
                $this->assertNotEmpty($url, "Route {$routeName} exists but returns empty URL");
            } catch (\Exception $e) {
                $this->fail("Route [{$routeName}] is used in navigation but not defined");
            }
        }
    }

    /**
     * Test that common route patterns follow naming conventions
     */
    public function test_route_naming_conventions(): void
    {
        $routes = Route::getRoutes();
        
        foreach ($routes as $route) {
            $routeName = $route->getName();
            if (!$routeName) continue;
            
            // Check that route names match their URI patterns
            if (str_contains($routeName, '.index')) {
                $uri = $route->uri();
                // Index routes should typically be at the root of their prefix
                if (!str_ends_with($uri, '{')) {
                    $parts = explode('.', $routeName);
                    $prefix = $parts[0];
                    
                    // Special cases that are ok
                    $exceptions = ['monitoring.transactions.index', 'api'];
                    if (!in_array($routeName, $exceptions) && 
                        !str_starts_with($routeName, 'api.') &&
                        !str_starts_with($routeName, 'filament.')) {
                        $this->assertStringEndsWith($prefix, $uri, 
                            "Route {$routeName} should have URI ending with '{$prefix}' but has '{$uri}'");
                    }
                }
            }
        }
    }

    /**
     * Test that there are no duplicate route names
     */
    public function test_no_duplicate_route_names(): void
    {
        $routes = Route::getRoutes();
        $routeNames = [];
        $duplicates = [];
        
        foreach ($routes as $route) {
            $name = $route->getName();
            if ($name) {
                if (isset($routeNames[$name])) {
                    $duplicates[] = $name;
                } else {
                    $routeNames[$name] = true;
                }
            }
        }
        
        $this->assertEmpty($duplicates, "Found duplicate route names: " . implode(', ', $duplicates));
    }
}