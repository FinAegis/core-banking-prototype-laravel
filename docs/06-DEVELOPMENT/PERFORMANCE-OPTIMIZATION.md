# Performance Optimization Guide

## Overview

This document outlines performance optimization strategies, techniques, and best practices implemented in the FinAegis Core Banking Platform. These optimizations ensure the system can handle enterprise-scale operations efficiently.

## Memory Management

### PHPStan Configuration

PHPStan requires significant memory for analyzing large codebases. We use a temporary directory strategy to prevent memory issues:

```bash
# Use isolated temp directory to prevent memory conflicts
TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse --memory-limit=2G
```

**Benefits:**
- Prevents memory conflicts between concurrent analyses
- Reduces memory fragmentation
- Enables parallel CI/CD pipelines

### PHP Memory Optimization

```php
// config/app.php
'memory_limit' => env('PHP_MEMORY_LIMIT', '512M'),
'max_execution_time' => env('PHP_MAX_EXECUTION_TIME', 300),
```

**Key Strategies:**
- Use generators for large datasets
- Implement cursor-based pagination
- Free resources explicitly in long-running processes
- Use `unset()` for large variables when done

## Testing Performance

### Parallel Test Execution

Pest PHP parallel testing significantly reduces test execution time:

```bash
# Run tests in parallel (recommended)
./vendor/bin/pest --parallel

# Configure parallel processes
./vendor/bin/pest --parallel --processes=10

# With coverage
./vendor/bin/pest --parallel --coverage --min=50
```

**Configuration:**
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

```php
// tests/Pest.php
uses(Tests\TestCase::class)
    ->beforeEach(function () {
        // Use in-memory SQLite for speed
        config(['database.default' => 'testing']);
    })
    ->in('Feature', 'Unit');
```

**Strategies:**
- Use in-memory databases for unit tests
- Mock external services
- Implement test data factories efficiently
- Clear caches between test suites

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

```php
// config/database.php
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

```php
// config/queue.php
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

```javascript
// vite.config.js
export default {
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    vendor: ['react', 'react-dom'],
                    utils: ['lodash', 'axios'],
                },
            },
        },
        minify: 'terser',
        terserOptions: {
            compress: {
                drop_console: true,
                drop_debugger: true,
            },
        },
    },
};
```

### Lazy Loading

```javascript
// Implement code splitting
const Dashboard = lazy(() => import('./pages/Dashboard'));
const Trading = lazy(() => import('./pages/Trading'));

// Use Suspense for loading states
<Suspense fallback={<Loading />}>
    <Routes>
        <Route path="/dashboard" element={<Dashboard />} />
        <Route path="/trading" element={<Trading />} />
    </Routes>
</Suspense>
```

## Caching Strategies

### Multi-Layer Caching

```php
// Application cache layers
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

```php
// app/Console/Commands/WarmCache.php
class WarmCache extends Command
{
    protected $signature = 'cache:warmup';
    
    public function handle(): void
    {
        // Warm critical caches
        $this->warmExchangeRates();
        $this->warmAccountBalances();
        $this->warmSystemSettings();
    }
    
    private function warmExchangeRates(): void
    {
        ExchangeRate::active()->each(function ($rate) {
            Cache::put("rate:{$rate->pair}", $rate, 300);
        });
    }
}
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

| Metric | Target | Current | Status |
|--------|--------|---------|--------|
| API Response Time | < 200ms | 150ms | ✅ |
| Page Load Time | < 3s | 2.5s | ✅ |
| Database Query Time | < 50ms | 35ms | ✅ |
| Test Suite Runtime | < 5min | 3min | ✅ |
| Memory Usage | < 512MB | 350MB | ✅ |
| CPU Usage | < 70% | 45% | ✅ |

### Load Testing

```bash
# Use Apache Bench for basic load testing
ab -n 1000 -c 10 http://localhost:8000/api/accounts

# Use k6 for advanced scenarios
k6 run load-test.js
```

## Best Practices

1. **Profile Before Optimizing**: Always measure performance before and after changes
2. **Cache Aggressively**: Cache expensive operations at multiple layers
3. **Optimize Database**: Use indexes, eager loading, and query optimization
4. **Async Processing**: Move heavy operations to background jobs
5. **Monitor Continuously**: Set up alerts for performance degradation
6. **Test Performance**: Include performance tests in CI/CD pipeline
7. **Document Changes**: Record all performance optimizations and their impact

## Troubleshooting

### Common Issues

1. **High Memory Usage**
   - Check for memory leaks in long-running processes
   - Review eager loading queries
   - Optimize cache sizes

2. **Slow Queries**
   - Enable query logging
   - Review database indexes
   - Consider query caching

3. **Slow Tests**
   - Use parallel testing
   - Mock external services
   - Use in-memory databases

4. **High CPU Usage**
   - Profile code with Xdebug
   - Optimize algorithms
   - Review infinite loops

## Resources

- [Laravel Performance Best Practices](https://laravel.com/docs/performance)
- [MySQL Performance Tuning](https://dev.mysql.com/doc/refman/8.0/en/optimization.html)
- [Redis Optimization](https://redis.io/docs/management/optimization/)
- [PHP Performance Tips](https://www.php.net/manual/en/features.performance.php)