<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level feature flag backed by a boolean config value.
 *
 * Usage: `feature:<config.key>` or `feature:<config.key>,redirect`.
 *
 * When the config value is truthy the request passes through unchanged. When
 * it is falsy the route answers 404, as if it were not registered, while its
 * name stays resolvable so `route()` calls in views and mail keep working.
 * With the optional `redirect` mode, GET/HEAD requests receive a 301 to the
 * site root instead (other methods still answer 404).
 *
 * The flag is read on every request, so it applies with cached routes too
 * (only `config:cache` is needed after changing the env value).
 */
final class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $configKey, ?string $mode = null): Response
    {
        if ((bool) config($configKey)) {
            return $next($request);
        }

        if ($mode === 'redirect' && ($request->isMethod('GET') || $request->isMethod('HEAD'))) {
            return redirect('/', 301);
        }

        abort(404);
    }
}
