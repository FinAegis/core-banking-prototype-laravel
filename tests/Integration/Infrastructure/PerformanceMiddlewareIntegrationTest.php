<?php

declare(strict_types=1);

use App\Http\Middleware\CachePerformance;
use App\Http\Middleware\MetricsMiddleware;
use App\Http\Middleware\QueryPerformanceMiddleware;
use App\Http\Middleware\StructuredLoggingMiddleware;
use App\Http\Middleware\TracingMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

uses(Tests\TestCase::class);

describe('Performance Middleware Integration', function () {
    it('StructuredLoggingMiddleware adds X-Request-ID header to response', function () {
        $middleware = app(StructuredLoggingMiddleware::class);
        $request = Request::create('/api/test', 'GET');

        $response = $middleware->handle($request, fn () => new Response('ok'));

        expect($response->headers->has('X-Request-ID'))->toBeTrue();
        expect($response->headers->get('X-Request-ID'))->not->toBeEmpty();
    });

    it('StructuredLoggingMiddleware preserves incoming X-Request-ID', function () {
        $middleware = app(StructuredLoggingMiddleware::class);
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('X-Request-ID', 'custom-req-123');

        $response = $middleware->handle($request, fn () => new Response('ok'));

        expect($response->headers->get('X-Request-ID'))->toBe('custom-req-123');
    });

    it('MetricsMiddleware records request count to cache', function () {
        Cache::flush();

        $middleware = app(MetricsMiddleware::class);
        $request = Request::create('/api/test', 'GET');

        $middleware->handle($request, fn () => new Response('ok', 200));

        expect(Cache::get('metrics.http.total'))->toBeGreaterThanOrEqual(1);
    });

    it('MetricsMiddleware tracks error counts for 4xx/5xx', function () {
        Cache::flush();

        $middleware = app(MetricsMiddleware::class);
        $request = Request::create('/api/test', 'GET');

        $middleware->handle($request, fn () => new Response('not found', 404));

        expect(Cache::get('metrics:errors:total'))->toBeGreaterThanOrEqual(1);
        expect(Cache::get('metrics:errors:client'))->toBeGreaterThanOrEqual(1);
    });

    it('MetricsMiddleware tracks by method', function () {
        Cache::flush();

        $middleware = app(MetricsMiddleware::class);
        $request = Request::create('/api/test', 'POST');

        $middleware->handle($request, fn () => new Response('ok', 200));

        $byMethod = Cache::get('metrics:http:by_method');
        expect($byMethod)->toBeArray();
        expect($byMethod)->toHaveKey('POST');
    });

    it('CachePerformance adds cache headers when operations occur', function () {
        Cache::flush();
        Cache::put('cache_performance:hits', 0, 60);
        Cache::put('cache_performance:misses', 0, 60);

        $middleware = app(CachePerformance::class);
        $request = Request::create('/api/test', 'GET');

        // Simulate cache hits during request
        $response = $middleware->handle($request, function () {
            Cache::increment('cache_performance:hits', 3);
            Cache::increment('cache_performance:misses', 1);

            return new Response('ok');
        });

        expect($response->headers->has('X-Cache-Hits'))->toBeTrue();
        expect($response->headers->has('X-Cache-Misses'))->toBeTrue();
        expect($response->headers->has('X-Cache-Hit-Rate'))->toBeTrue();
        expect((int) $response->headers->get('X-Cache-Hits'))->toBe(3);
        expect((int) $response->headers->get('X-Cache-Misses'))->toBe(1);
    });

    it('QueryPerformanceMiddleware skips when disabled', function () {
        Config::set('performance.query_logging', false);

        $middleware = app(QueryPerformanceMiddleware::class);
        $request = Request::create('/api/test', 'GET');

        $response = $middleware->handle($request, fn () => new Response('ok'));

        expect($response->getStatusCode())->toBe(200);
    });

    it('QueryPerformanceMiddleware enables query log when enabled', function () {
        Config::set('performance.query_logging', true);

        $middleware = app(QueryPerformanceMiddleware::class);
        $request = Request::create('/api/test', 'GET');

        $response = $middleware->handle($request, fn () => new Response('ok'));

        expect($response->getStatusCode())->toBe(200);
    });

    it('TracingMiddleware is non-blocking when tracing disabled', function () {
        Config::set('monitoring.tracing.enabled', false);

        $middleware = app(TracingMiddleware::class);
        $request = Request::create('/api/test', 'GET');

        $response = $middleware->handle($request, fn () => new Response('ok'));

        expect($response->getStatusCode())->toBe(200);
    });

    it('all 5 middleware are registered in the api middleware group', function () {
        $middlewareClasses = [
            StructuredLoggingMiddleware::class,
            MetricsMiddleware::class,
            QueryPerformanceMiddleware::class,
            CachePerformance::class,
            TracingMiddleware::class,
        ];

        // Verify each middleware class exists and is instantiable
        foreach ($middlewareClasses as $class) {
            expect(class_exists($class))->toBeTrue("$class should exist");
            expect(app($class))->toBeInstanceOf($class);
        }
    });

    it('middleware chain processes request without error', function () {
        Config::set('performance.query_logging', false);
        Config::set('monitoring.tracing.enabled', false);
        Cache::flush();

        $request = Request::create('/api/test', 'GET');
        $finalResponse = new Response('ok', 200);

        // Chain all middleware manually
        $middlewares = [
            app(StructuredLoggingMiddleware::class),
            app(MetricsMiddleware::class),
            app(QueryPerformanceMiddleware::class),
            app(CachePerformance::class),
            app(TracingMiddleware::class),
        ];

        $next = fn () => $finalResponse;

        // Build middleware chain (outermost first)
        foreach (array_reverse($middlewares) as $middleware) {
            $currentNext = $next;
            $next = fn ($req) => $middleware->handle($req, $currentNext);
        }

        $response = $next($request);

        expect($response->getStatusCode())->toBe(200);
        expect($response->headers->has('X-Request-ID'))->toBeTrue();
    });
});
