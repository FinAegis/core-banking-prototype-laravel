<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Monitoring\Services\MetricsCollector;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MetricsMiddleware
{
    public function __construct(
        private readonly MetricsCollector $metrics
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);

        // Increment request counter
        Cache::increment('metrics:requests:total');

        $response = $next($request);

        $duration = microtime(true) - $startTime;

        // Record request metrics
        $this->metrics->recordHttpRequest(
            method: $request->method(),
            route: $request->route()?->getName() ?? 'unknown',
            statusCode: $response->status(),
            duration: $duration
        );

        // Track error rates
        if ($response->status() >= 400) {
            Cache::increment('metrics:errors:total');

            if ($response->status() >= 500) {
                Cache::increment('metrics:errors:server');
            } else {
                Cache::increment('metrics:errors:client');
            }
        }

        // Cache metrics
        if ($request->route()) {
            $routeName = $request->route()->getName() ?? $request->path();
            Cache::put("metrics:requests:duration:{$routeName}", $duration, 60);
        }

        return $response;
    }
}
