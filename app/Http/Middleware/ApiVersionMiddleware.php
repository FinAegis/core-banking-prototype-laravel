<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiVersionMiddleware
{
    /**
     * Handle an incoming request — inject API version and deprecation headers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $version = $this->extractVersion($request);

        $response->headers->set('X-API-Version', $version);

        $versionConfig = config("api-versioning.versions.{$version}");

        if (! is_array($versionConfig)) {
            return $response;
        }

        if (! empty($versionConfig['deprecated'])) {
            $deprecatedAt = $versionConfig['deprecated_at'] ?? 'true';
            $response->headers->set('Deprecation', (string) $deprecatedAt);
        }

        if (! empty($versionConfig['sunset'])) {
            $response->headers->set('Sunset', (string) $versionConfig['sunset']);
        }

        return $response;
    }

    /**
     * Extract API version from the request path (e.g. /api/v2/accounts → v2).
     */
    private function extractVersion(Request $request): string
    {
        $path = $request->path();

        if (preg_match('#(?:^|/)v(\d+)(?:/|$)#', $path, $matches)) {
            return 'v' . $matches[1];
        }

        return (string) config('api-versioning.current_version', 'v1');
    }
}
