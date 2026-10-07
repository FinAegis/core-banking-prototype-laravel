<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Infrastructure\Domain\DomainManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level gate on the MODULES_DISABLED list (config/modules.php).
 *
 * Usage: `module.enabled:<Domain>` (bare directory name or finaegis/{name},
 * matched case-insensitively like the ModuleRouteLoader).
 *
 * For routes that live outside app/Domain/{Name}/Routes/api.php (web pages,
 * shared route files) and are therefore not covered by the module route
 * loader. A disabled module answers 404 while the route name stays
 * registered, so `route()` calls in shared layouts keep resolving.
 *
 * Reads config only (DomainManager::isDisabledByConfig), evaluated per
 * request, so it also applies with cached routes.
 */
final class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (DomainManager::isDisabledByConfig($module)) {
            abort(404);
        }

        return $next($request);
    }
}
