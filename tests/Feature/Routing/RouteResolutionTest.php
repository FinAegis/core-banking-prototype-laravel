<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Route resolution guard
|--------------------------------------------------------------------------
|
| Walks every registered route and checks three things:
|
| 1. The controller action of each route names a class and a public method
|    that exist.
| 2. No route is unreachable because an earlier route with a different
|    action matches the same request first.
| 3. Every literal route name used in a Blade view or in app code is
|    registered.
|
| Skipped explicitly:
| - Closure routes (they have no controller to resolve).
| - Routes served by vendor packages: Filament, Livewire, Passport, Horizon,
|   Telescope, Sanctum, Lighthouse and l5-swagger. Their controllers belong
|   to the packages and are tested there.
| - MCP subdomain routes (app/Domain/MCP/Routes/api.php). bootstrap/app.php
|   registers them only when the request host starts with "mcp.", so they
|   are not in the route table under test; tests/Feature/MCP covers them.
|
*/

/**
 * Controller namespaces that belong to vendor packages.
 *
 * @return list<string>
 */
function routeResolutionVendorPrefixes(): array
{
    return [
        'Filament\\',
        'Livewire\\',
        'Laravel\\Passport\\',
        'Laravel\\Horizon\\',
        'Laravel\\Telescope\\',
        'Laravel\\Sanctum\\',
        'Laravel\\Fortify\\',
        'Laravel\\Jetstream\\',
        'Nuwave\\Lighthouse\\',
        'MLL\\GraphiQL\\',
        'L5Swagger\\',
    ];
}

/**
 * The controller class and method a route calls, or null when the route is
 * skipped (closure or vendor package).
 *
 * @return array{0: string, 1: string}|null
 */
function routeResolutionControllerAction(RoutingRoute $route): ?array
{
    $uses = $route->getAction('uses');
    if (! is_string($uses)) {
        return null;
    }

    if (str_contains($uses, '@')) {
        [$class, $method] = explode('@', $uses, 2);
    } else {
        $class = $uses;
        $method = '__invoke';
    }

    $class = ltrim($class, '\\');
    foreach (routeResolutionVendorPrefixes() as $prefix) {
        if (str_starts_with($class, $prefix)) {
            return null;
        }
    }

    return [$class, $method];
}

it('resolves every route action to an existing public controller method', function (): void {
    $broken = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        $action = routeResolutionControllerAction($route);
        if ($action === null) {
            continue;
        }

        [$class, $method] = $action;
        $label = implode('|', $route->methods()) . ' /' . ltrim($route->uri(), '/') . " -> {$class}@{$method}";

        if (! class_exists($class)) {
            $broken[] = "{$label} (class does not exist)";
            continue;
        }

        if (! method_exists($class, $method)) {
            $broken[] = "{$label} (method does not exist)";
            continue;
        }

        $reflection = new ReflectionMethod($class, $method);
        if (! $reflection->isPublic() || $reflection->isAbstract() || $reflection->isStatic()) {
            $broken[] = "{$label} (method is not a public instance method)";
        }
    }

    expect($broken)->toBe([], "Routes whose controller action does not exist:\n" . implode("\n", $broken));
});

it('does not let an earlier route with a different action capture a later route', function (): void {
    // Known conflicts that need an owner decision rather than a mechanical
    // fix. Each entry is "METHOD uri". Remove the entry once fixed.
    $known = [
        // routes/api-v2.php (BankIntegrationController) and
        // app/Domain/Banking/Routes/api.php (BankingController) both
        // register /api/v2/banks/*. Same URIs collapse onto one route, but
        // these two use different parameter names, so the v2 file wins.
        'DELETE api/v2/banks/disconnect/{connectionId}',
        'POST api/v2/banks/accounts/sync/{connectionId}',
    ];

    $samples = ['sample-value', '12345', '9b2f3c1e-4d5a-4b6c-8d7e-0f1a2b3c4d5e', 'USD'];
    $routes = Route::getRoutes();
    $shadowed = [];

    foreach ($routes->getRoutes() as $route) {
        foreach ($route->methods() as $method) {
            if ($method === 'HEAD' || $route->isFallback) {
                continue;
            }

            // Build a request this route matches by filling its parameters.
            $request = null;
            foreach ($samples as $sample) {
                $uri = (string) preg_replace('/\{[^}]+\}/', $sample, $route->uri());
                $host = $route->getDomain() !== null
                    ? (string) preg_replace('/\{[^}]+\}/', 'sample', $route->getDomain())
                    : 'localhost';
                $candidate = Request::create('http://' . $host . '/' . ltrim($uri, '/'), $method);
                if ($route->matches($candidate)) {
                    $request = $candidate;
                    break;
                }
            }

            if ($request === null) {
                // A parameter constraint rejects every sample value.
                continue;
            }

            $first = null;
            foreach ($routes->get($method) as $candidateRoute) {
                if (! $candidateRoute->isFallback && $candidateRoute->matches($request)) {
                    $first = $candidateRoute;
                    break;
                }
            }

            if ($first === null || $first === $route) {
                continue;
            }

            // Two registrations of the same action are redundant but harmless.
            if ($first->getAction('uses') === $route->getAction('uses')) {
                continue;
            }

            $key = $method . ' ' . $route->uri();
            if (in_array($key, $known, true)) {
                continue;
            }

            $shadowed[] = "{$key} is captured by {$method} {$first->uri()}";
        }
    }

    expect($shadowed)->toBe([], "Routes captured by an earlier route:\n" . implode("\n", $shadowed));
});

it('only uses registered route names in views and app code', function (): void {
    $patterns = [
        // route('name') helper, but not $request->route('param') or Foo::route()
        '/(?<![>\w$:])route\(\s*\'([^\'$]+)\'\s*[,)]/',
        '/redirect\(\)\s*->\s*route\(\s*\'([^\'$]+)\'\s*[,)]/',
        '/to_route\(\s*\'([^\'$]+)\'\s*[,)]/',
    ];

    $missing = [];

    foreach (['resources/views', 'app'] as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory)));

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $path = $file->getPathname();
            if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
                continue;
            }

            if (str_ends_with($path, '.blade.php')) {
                // Blade comments are not rendered.
                $source = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($path));
            } else {
                // Commented-out PHP code is not run.
                $source = php_strip_whitespace($path);
            }

            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $source, $matches) === false) {
                    continue;
                }

                foreach ($matches[1] as $name) {
                    if (! Route::has($name)) {
                        $missing[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path) . ": {$name}";
                    }
                }
            }
        }
    }

    $missing = array_values(array_unique($missing));

    expect($missing)->toBe([], "Route names that are not registered:\n" . implode("\n", $missing));
});
