# Performance Optimization Guide

## Overview

This guide describes ways to keep FinAegis fast, from memory settings and tests to the database, queues, caching and the web server. Some sections describe what the project already does. Others are general Laravel practices shown with example code, and the guide says which is which.

## Memory Management

### PHPStan Configuration

PHPStan needs a lot of memory for a codebase of this size. The project runs it at Level 8 with this command:

```bash
XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G

# When several analyses run at once, give each its own temp directory
XDEBUG_MODE=off TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse --memory-limit=2G
```

Turning Xdebug off makes the analysis much faster. A separate temp directory keeps concurrent runs from sharing cache files.

### PHP Memory Optimization

Set the memory and time limits in `php.ini` or the PHP-FPM pool. `config/app.php` has no such settings.

```ini
; php.ini
memory_limit = 512M
max_execution_time = 300
```

Use generators or `cursor()` for large datasets and cursor-based pagination for long lists. Free resources in long-running processes, and `unset()` large variables when you are done with them.

## Testing Performance

### Parallel Test Execution

Running Pest in parallel makes the suite much faster. See [PARALLEL-TESTING.md](PARALLEL-TESTING.md) for details.

```bash
# Run tests in parallel (recommended)
./vendor/bin/pest --parallel

# Configure parallel processes
./vendor/bin/pest --parallel --processes=10

# With coverage (local only; CI does not collect coverage)
./vendor/bin/pest --parallel --coverage
```

**Coverage report configuration (example):**
```xml
<!-- phpunit.xml -->
<phpunit>
    <coverage>
        <report>
            <html outputDirectory="coverage-html"/>
            <text outputFile="php://stdout" showOnlySummary="true"/>
        </report>
    </coverage>
</phpunit>
```

### Test Suite Optimization

`phpunit.xml` already sets `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`, so most tests run on an in-memory database. `tests/Pest.php` binds the base test cases to the directories:

```php
// tests/Pest.php (excerpt)
uses(TestCase::class)->in('Feature');
uses(TestCase::class)->in('Domain');
uses(TestCase::class)->in('Console');
uses(Tests\MultiConnection\MultiConnectionTestCase::class)->in('MultiConnection');
```

Mock external services, keep factories lean and clear caches between test suites.

## Database Optimization

### Query Optimization

```php
// Use eager loading to prevent N+1 queries
$accounts = Account::with(['user', 'transactions', 'balances'])
    ->where('status', 'active')
    ->cursor(); // Use cursor for large datasets

// Index optimization in migrations
Schema::table('transactions', function (Blueprint $table) {
    $table->index(['account_id', 'created_at']);
    $table->index(['status', 'type']);
});
```

### Connection Pooling

The project's `config/database.php` does not turn on persistent connections. This example shows the option:

```php
// config/database.php (example)
'mysql' => [
    'driver' => 'mysql',
    'options' => [
        PDO::ATTR_PERSISTENT => true, // Use persistent connections
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false, // Unbuffered queries for large results
    ],
],
```

### Database Caching

```php
// Use query caching for frequently accessed data
$exchangeRates = Cache::remember('exchange_rates', 300, function () {
    return ExchangeRate::where('active', true)->get();
});

// Tagged caching for granular invalidation
Cache::tags(['accounts', 'user-' . $userId])
    ->remember($key, 3600, function () {
        return Account::calculateBalance();
    });
```

## Application Performance

### Laravel Optimization Commands

```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer install --optimize-autoloader --no-dev

# Combined optimization
php artisan optimize
```

### Queue Optimization

This is an example of Redis queue settings. Horizon manages the workers in production.

```php
// config/queue.php (example)
'redis' => [
    'driver' => 'redis',
    'connection' => 'queue',
    'queue' => env('REDIS_QUEUE', 'default'),
    'retry_after' => 90,
    'block_for' => 5, // Long polling for efficiency
    'after_commit' => true, // Dispatch after DB commit
],

// Use multiple queues for prioritization
'queues' => [
    'high' => ['memory' => 128, 'timeout' => 60],
    'default' => ['memory' => 256, 'timeout' => 120],
    'low' => ['memory' => 512, 'timeout' => 300],
],
```

### Event Sourcing Performance

The examples below show batch inserts and queued projections. Many projectors in the project are already queued. Aggregates with many events can use snapshots (`php artisan snapshot:create`).

```php
// Batch event processing
class EventProcessor
{
    public function processBatch(array $events): void
    {
        DB::transaction(function () use ($events) {
            foreach (array_chunk($events, 1000) as $chunk) {
                Event::insert($chunk);
            }
        });
    }
}

// Async event projection
class ProjectionJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;
    
    public function handle(): void
    {
        // Process projections asynchronously
    }
}
```

## Frontend Performance

### Asset Optimization

The web front end is built from Blade views with Livewire and Vite. `vite.config.js` splits the chart libraries into their own chunk and keeps the hardware wallet libraries out of the bundle:

```javascript
// vite.config.js (excerpt)
build: {
    chunkSizeWarningLimit: 200,
    rollupOptions: {
        external: [
            '@ledgerhq/hw-transport-webusb',
            '@ledgerhq/hw-app-eth',
            '@trezor/connect-web',
        ],
        output: {
            manualChunks(id) {
                // Split chart libraries into separate chunk
                if (id.includes('chart.js') || id.includes('chartjs')) {
                    return 'vendor-charts';
                }
            },
        },
    },
},
```

The landing page uses a hand-compiled stylesheet, `public/css/app-landing.css`. Rebuild it with the Tailwind CLI after you change classes in its Blade view.

### Lazy Loading

Load heavy scripts only on the pages that need them. With Vite, use a dynamic `import()` inside the page script:

```javascript
// Load the chart code only when the chart element exists
if (document.querySelector('#flowChart')) {
    const { default: Chart } = await import('chart.js/auto');
}
```

## Caching Strategies

### Multi-Layer Caching

This is an example pattern. The project does not have a `CacheService` class like this one.

```php
// Application cache layers (example)
class CacheService
{
    private array $layers = [
        'memory' => ArrayCache::class,      // L1: In-memory
        'redis' => RedisCache::class,       // L2: Redis
        'database' => DatabaseCache::class, // L3: Database
    ];
    
    public function get(string $key): mixed
    {
        foreach ($this->layers as $layer) {
            if ($value = $layer::get($key)) {
                $this->warmUpperLayers($key, $value);
                return $value;
            }
        }
        return null;
    }
}
```

### Cache Warming

The project has a `cache:warmup` command in `app/Console/Commands/CacheWarmup.php`. It warms the Redis cache for accounts:

```bash
# Warm all accounts that are not frozen
php artisan cache:warmup

# Warm specific accounts
php artisan cache:warmup --account=<uuid> --account=<uuid>
```

## Monitoring & Profiling

### Performance Monitoring

```php
// Use Laravel Telescope in development
if (app()->environment('local')) {
    Telescope::record(function () {
        return [
            'queries' => DB::getQueryLog(),
            'memory' => memory_get_peak_usage(true),
            'time' => microtime(true) - LARAVEL_START,
        ];
    });
}

// Custom performance metrics
class PerformanceMiddleware
{
    public function handle($request, Closure $next)
    {
        $start = microtime(true);
        
        $response = $next($request);
        
        $duration = microtime(true) - $start;
        
        if ($duration > 1.0) {
            Log::warning('Slow request', [
                'url' => $request->url(),
                'duration' => $duration,
                'memory' => memory_get_peak_usage(true),
            ]);
        }
        
        return $response;
    }
}
```

### Query Monitoring

```php
// Enable query logging in development
if (app()->environment('local')) {
    DB::listen(function ($query) {
        if ($query->time > 100) {
            Log::warning('Slow query detected', [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time' => $query->time,
            ]);
        }
    });
}
```

## Production Optimizations

### OPcache Configuration

```ini
; php.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
opcache.save_comments=0
opcache.fast_shutdown=1
```

### Redis Optimization

```conf
# redis.conf
maxmemory 2gb
maxmemory-policy allkeys-lru
save ""  # Disable persistence for cache-only instances
tcp-keepalive 60
tcp-backlog 511
```

### Nginx Configuration

```nginx
# nginx.conf
worker_processes auto;
worker_connections 2048;

http {
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    
    # Enable gzip
    gzip on;
    gzip_types text/plain application/json application/javascript text/css;
    gzip_min_length 1000;
    
    # Cache static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

## Performance Benchmarks

### Target Metrics

These are targets. The project does not publish measured values against them.

| Metric | Target |
|--------|--------|
| API Response Time | < 200ms |
| Page Load Time | < 3s |
| Database Query Time | < 50ms |
| Memory Usage | < 512MB |
| CPU Usage | < 70% |

The full test suite takes much longer than five minutes. CI splits it into parallel jobs and shards.

### Load Testing

```bash
# Use Apache Bench for basic load testing
ab -n 1000 -c 10 http://localhost:8000/api/accounts

# Use k6 for advanced scenarios (the Performance workflow runs this script)
k6 run tests/k6/load-test.js
```

## Best Practices

1. Measure performance before and after every change.
2. Cache expensive operations.
3. Use indexes, eager loading and well-shaped queries.
4. Move heavy work to background jobs.
5. Set up alerts for performance degradation.
6. Keep the performance tests in CI.
7. Record each optimization and its effect.

## Troubleshooting

### Common Issues

1. **High memory usage.** Look for memory leaks in long-running processes, review queries that eager-load a lot and check the cache sizes.
2. **Slow queries.** Turn on query logging, review the indexes and consider caching the result.
3. **Slow tests.** Run them in parallel, mock external services and use the in-memory database.
4. **High CPU usage.** Profile the code with Xdebug, improve the algorithms and look for loops that never end.

## Resources

- [Laravel Performance Best Practices](https://laravel.com/docs/performance)
- [MySQL Performance Tuning](https://dev.mysql.com/doc/refman/8.0/en/optimization.html)
- [Redis Optimization](https://redis.io/docs/management/optimization/)
- [PHP Performance Tips](https://www.php.net/manual/en/features.performance.php)
