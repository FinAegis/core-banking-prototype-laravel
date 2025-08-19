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

        try {
            $response = $next($request);
            $duration = microtime(true) - $startTime;

            // Record request metrics using the MetricsCollector
            $this->metrics->recordHttpRequest(
                method: $request->method(),
                route: $request->path(),
                statusCode: $response->status(),
                duration: $duration
            );

            // Track additional metrics for tests (these should already be handled by recordHttpRequest)
            // Cache::increment('metrics:http:requests:total'); // Already done in recordHttpRequest
            Cache::put('metrics:http:duration', $duration, 60);

            // Track by method
            $byMethod = Cache::get('metrics:http:by_method', []);
            $byMethod[$request->method()] = ($byMethod[$request->method()] ?? 0) + 1;
            Cache::put('metrics:http:by_method', $byMethod, 60);

            // Track by path
            $byPath = Cache::get('metrics:http:by_path', []);
            $byPath[$request->path()] = ($byPath[$request->path()] ?? 0) + 1;
            Cache::put('metrics:http:by_path', $byPath, 60);

            // Track error rates (some already handled by recordHttpRequest)
            if ($response->status() >= 400) {
                Cache::increment('metrics:errors:total');
                Cache::increment('metrics.http.errors');

                if ($response->status() >= 500) {
                    Cache::increment('metrics:errors:server');
                } else {
                    Cache::increment('metrics:errors:client');
                }
            }
            // Success count already handled by recordHttpRequest

            // Update requests total for legacy test
            Cache::increment('metrics.http.total');
            Cache::put('metrics:requests:total', Cache::get('metrics:http:requests:total', 0), 60);

            return $response;
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;

            // Record exception metrics
            $this->metrics->recordHttpRequest(
                method: $request->method(),
                route: $request->path(),
                statusCode: 500,
                duration: $duration
            );

            // Update cache for tests
            Cache::increment('metrics.http.total');
            Cache::increment('metrics.http.errors');

            throw $e;
        }
    }
}
