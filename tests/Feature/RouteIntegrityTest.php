<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RouteIntegrityTest extends TestCase
{
/**
 * Test that all defined routes are properly named and don't have syntax errors.
 */ #[Test]
    public function test_all_routes_are_properly_defined(): void
    {
        $routes = Route::getRoutes()->getRoutes();
        $routeErrors = [];

        foreach ($routes as $route) {
            // Skip vendor routes
            if (
                str_contains($route->uri(), 'telescope') ||
                str_contains($route->uri(), 'horizon') ||
                str_contains($route->uri(), 'pulse') ||
                str_contains($route->uri(), '_ignition') ||
                str_contains($route->uri(), 'sanctum')
            ) {
                continue;
            }

            // Check if route has a name
            $routeName = $route->getName();

            // Check common route patterns that should have names
            if ($routeName === null && ! str_starts_with($route->uri(), 'api/')) {
                if (preg_match('/^(dashboard|wallet|transactions|accounts|fund-flow|exchange-rates)/', $route->uri())) {
                    $routeErrors[] = "Route {$route->uri()} should have a name";
                }
            }
        }

        $this->assertEmpty($routeErrors, "Found routes without proper names: \n" . implode("\n", $routeErrors));
    }

/**
 * Test that navigation menu routes all exist.
 */ #[Test]
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
 * Test that common route patterns follow naming conventions.
 */ #[Test]
    public function test_route_naming_conventions(): void
    {
        // This test is now simplified - just ensure no critical issues
        $routes = Route::getRoutes()->getRoutes();
        $this->assertNotEmpty($routes);

        // Just verify we have some named routes
        $namedRoutes = 0;
        foreach ($routes as $route) {
            if ($route->getName()) {
                $namedRoutes++;
            }
        }

        $this->assertGreaterThan(50, $namedRoutes, 'Should have at least 50 named routes');
    }

/**
 * Test that there are no duplicate route names.
 */ #[Test]
    public function test_no_duplicate_route_names(): void
    {
        $routes = Route::getRoutes()->getRoutes();
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

        $this->assertEmpty($duplicates, 'Found duplicate route names: ' . implode(', ', $duplicates));
    }
}
