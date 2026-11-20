<?php

declare(strict_types=1);

namespace Tests\Traits;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;
use ReflectionProperty;

/**
 * CI runs the suite in parallel processes that share one Redis cache
 * (phpunit.ci-optimized.xml). Counters, rate limiters and IP blocks written
 * by one process are then seen by another, whose users are numbered from 1
 * as well, so a test can start with a limit already reached.
 *
 * isolate() moves the default cache store to a per-process array store and
 * swaps the rate limiter for one on that store. The named limiters
 * registered at boot (RateLimiter::for) are copied over, because routes
 * throttle by name and an unknown name would refuse every request.
 */
final class SharedCacheIsolation
{
    public static function isolate(): void
    {
        config(['cache.default' => 'array']);

        $current = app(RateLimiter::class);
        $isolated = new RateLimiter(Cache::store('array'));

        $limiters = new ReflectionProperty(RateLimiter::class, 'limiters');
        $limiters->setValue($isolated, $limiters->getValue($current));

        RateLimiterFacade::swap($isolated);
    }
}
