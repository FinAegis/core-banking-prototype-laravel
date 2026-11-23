# Performance Optimization Guide

## Overview

This guide explains how to measure and tune the performance of a FinAegis deployment. It covers the load test commands, the CI performance jobs and common optimizations for the database, cache, queues and web server.

## Performance Targets

These numbers are targets to tune a deployment against. They are not measured results.

### API Response Times
- **Account Creation**: < 100ms
- **Transfers**: < 200ms  
- **Exchange Rate Lookup**: < 50ms
- **Webhook Operations**: < 50ms
- **Complex Queries**: < 100ms
- **Cache Operations**: < 1ms

### Throughput Goals
- **Transactions per Second**: 10,000+ TPS
- **Concurrent Users**: 100,000+
- **Daily Transactions**: 1 billion+

## Running Performance Tests

### Load Testing Command

```bash
# Run all performance tests
php artisan test:load --report

# Run specific tests with custom parameters
php artisan test:load \
  --test=transfers \
  --test=exchange-rates \
  --iterations=1000 \
  --concurrent=50 \
  --report \
  --benchmark

# Compare benchmarks
php artisan test:compare-benchmarks \
  storage/app/benchmarks/baseline.json \
  --threshold=5
```

`test:load --benchmark` saves its results to `storage/app/benchmarks/load-test-<timestamp>.json`. The repository has no `baseline.json`. Copy a run you trust to that name to use it as the baseline. Without a second file argument, `test:compare-benchmarks` compares against the latest run. The default regression threshold is 10 percent.

### Continuous Performance Monitoring

The CI pipeline (`.github/workflows/ci-pipeline.yml`) runs the performance workflow `05-performance.yml` on every push and pull request to `main` and `develop`, and on manual dispatch. There is no daily scheduled run. The workflow runs `./vendor/bin/pest tests/Performance`, Apache Bench checks against `php artisan serve` and an advisory k6 load test from `tests/k6/`.

## Optimization Strategies

### 1. Database Optimization

#### Indexing Strategy
The migrations already create the main lookup indexes. `stored_events` and `transactions` have a unique index on `(aggregate_uuid, aggregate_version)`. `account_balances` has a unique index on `(account_uuid, asset_code)`, and `exchange_rates` has one on `(from_asset_code, to_asset_code, valid_at)`. Add more indexes only for slow queries you have measured, for example:

```sql
-- Example; check EXPLAIN output first
CREATE INDEX idx_transactions_created ON transactions(created_at);
```

#### Query Optimization
```php
// Bad: N+1 query problem
$accounts = Account::all();
foreach ($accounts as $account) {
    $balances = $account->balances; // Additional query per account
}

// Good: Eager loading
$accounts = Account::with('balances')->get();

// Better: Specific selection
$accounts = Account::with(['balances' => function ($query) {
    $query->where('balance', '>', 0);
}])->select('uuid', 'name', 'user_uuid')->get();
```

#### Connection Pooling
```php
// config/database.php
'mysql' => [
    'options' => [
        PDO::ATTR_PERSISTENT => true,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ],
    // Laravel's MySQL driver has no connection pool setting.
    // Use persistent connections, Octane, or a proxy such as ProxySQL.
],
```

### 2. Caching Strategy

#### Multi-Level Caching
```php
// Application-level cache
class AccountCacheService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const CACHE_WARMING_THRESHOLD = 300; // 5 minutes
    
    public function getAccount(string $uuid): ?Account
    {
        return Cache::remember(
            $this->getCacheKey($uuid),
            self::CACHE_TTL,
            fn() => Account::with('balances')->find($uuid)
        );
    }
    
    public function warmCache(string $uuid): void
    {
        $ttl = Cache::ttl($this->getCacheKey($uuid));
        
        if ($ttl < self::CACHE_WARMING_THRESHOLD) {
            dispatch(new WarmAccountCacheJob($uuid));
        }
    }
}
```

#### Redis Optimization
```bash
# Redis configuration for performance
maxmemory 4gb
maxmemory-policy allkeys-lru
save ""  # Disable persistence for cache-only instances
```

#### Cache Tagging
```php
// Group related cache entries
Cache::tags(['accounts', "user:{$userId}"])->put($key, $account, 3600);

// Invalidate all user's accounts
Cache::tags(["user:{$userId}"])->flush();
```

### 3. Queue Optimization

#### Queue Configuration
```php
// config/queue.php
'redis' => [
    'driver' => 'redis',
    'connection' => 'queue',
    'queue' => env('REDIS_QUEUE', 'default'),
    'retry_after' => 90,
    'block_for' => 5,
    'after_commit' => true,
],

// Queues the workers must cover (config/queue.php managed_queues)
'managed_queues' => [
    'default', 'events', 'ledger', 'transactions', 'transfers',
    'liquidity_pools', 'mobile', 'broadcasts', 'webhooks',
    'fraud-batch', 'exchange', 'proofs',
],
```

#### Worker Optimization
```bash
# Worker for the money-moving queues (run several processes under Supervisor)
php artisan queue:work redis \
  --queue=transfers,transactions,ledger,events \
  --tries=3 \
  --max-jobs=1000 \
  --max-time=3600 \
  --memory=512 \
  --sleep=1
```

### 4. API Optimization

#### Response Compression
```php
// Middleware for response compression
class CompressResponse
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        
        if ($this->shouldCompress($request, $response)) {
            $response->header('Content-Encoding', 'gzip');
            $response->setContent(gzencode($response->getContent(), 9));
        }
        
        return $response;
    }
}
```

#### Pagination
```php
// Always paginate large datasets
public function index(Request $request)
{
    return AccountResource::collection(
        Account::with('balances')
            ->paginate($request->input('per_page', 50))
            ->appends($request->query())
    );
}
```

#### Field Selection
```php
// Allow clients to specify needed fields
public function show(Request $request, string $uuid)
{
    $fields = $request->input('fields', ['*']);
    
    return new AccountResource(
        Account::select($fields)
            ->with($this->getRequiredRelations($fields))
            ->findOrFail($uuid)
    );
}
```

### 5. Event Sourcing Optimization

#### Snapshot Strategy
```php
// Create snapshots for high-traffic aggregates
class SnapshotOptimizer
{
    private const EVENTS_THRESHOLD = 100;
    
    public function shouldSnapshot(string $aggregateUuid): bool
    {
        $eventCount = StoredEvent::where('aggregate_uuid', $aggregateUuid)
            ->where('created_at', '>', $this->getLastSnapshotDate($aggregateUuid))
            ->count();
            
        return $eventCount > self::EVENTS_THRESHOLD;
    }
}
```

#### Event Store Partitioning
```sql
-- Partition stored_events by month
ALTER TABLE stored_events PARTITION BY RANGE (YEAR(created_at) * 100 + MONTH(created_at)) (
    PARTITION p202501 VALUES LESS THAN (202502),
    PARTITION p202502 VALUES LESS THAN (202503),
    -- ... more partitions
);
```

### 6. Application Optimization

#### Lazy Loading
```php
// Load expensive data only when needed
class Account extends Model
{
    protected static function booted()
    {
        static::retrieved(function ($account) {
            // Don't load heavy relationships by default
            $account->makeHidden(['transactions', 'audit_logs']);
        });
    }
    
    public function getTransactionsAttribute()
    {
        // Load only when accessed
        return $this->loadMissing('transactions')->getRelation('transactions');
    }
}
```

#### Memory Management
```php
// Process large datasets in chunks
Account::chunk(1000, function ($accounts) {
    foreach ($accounts as $account) {
        // Process account
    }
    
    // Free memory after each chunk
    unset($accounts);
    gc_collect_cycles();
});
```

### 7. Infrastructure Optimization

#### PHP Configuration
```ini
; php.ini optimizations
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.preload=/path/to/preload.php

; Increase limits for high load
memory_limit=512M
max_execution_time=30
```

#### Web Server Tuning
```nginx
# Nginx optimization
worker_processes auto;
worker_connections 4096;

# Enable caching
location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}

# Enable compression
gzip on;
gzip_types text/plain application/json application/javascript text/css;
gzip_min_length 1000;
```

## Monitoring and Alerting

### Performance Metrics

The app exports its own metrics at `/api/monitoring/prometheus` (see [MONITORING/monitoring.md](MONITORING/monitoring.md)). The class below is an illustration of sending timings to an external StatsD service. It is not part of the codebase.

```php
// Track key metrics
class PerformanceMonitor
{
    public function recordMetric(string $operation, float $duration): void
    {
        // Send to monitoring service
        StatsD::timing("app.{$operation}.duration", $duration);
        
        // Alert if threshold exceeded
        if ($duration > $this->getThreshold($operation)) {
            Log::warning("Performance threshold exceeded", [
                'operation' => $operation,
                'duration' => $duration,
                'threshold' => $this->getThreshold($operation),
            ]);
        }
    }
}
```

### Health Checks

The app already has `/api/monitoring/health`, `/api/monitoring/ready` and `/api/monitoring/alive`. The sample below shows the pattern.

```php
// Comprehensive health check endpoint
public function health()
{
    $checks = [
        'database' => $this->checkDatabase(),
        'redis' => $this->checkRedis(),
        'queue' => $this->checkQueue(),
        'storage' => $this->checkStorage(),
    ];
    
    $status = collect($checks)->every(fn($check) => $check['status'] === 'ok') 
        ? 'healthy' 
        : 'unhealthy';
    
    return response()->json([
        'status' => $status,
        'checks' => $checks,
        'timestamp' => now()->toIso8601String(),
    ], $status === 'healthy' ? 200 : 503);
}
```

## Performance Testing Checklist

### Before Deployment
- [ ] Run full load test suite
- [ ] Compare benchmarks with baseline
- [ ] Review slow query log
- [ ] Check cache hit rates
- [ ] Validate queue processing times
- [ ] Test under expected peak load

### After Deployment
- [ ] Monitor response times
- [ ] Check error rates
- [ ] Validate cache performance
- [ ] Review queue backlogs
- [ ] Analyze database connections
- [ ] Track memory usage

## Common Performance Issues

### 1. N+1 Query Problem
**Symptom**: Multiple database queries for related data
**Solution**: Use eager loading with `with()` method

### 2. Missing Indexes
**Symptom**: Slow queries on large tables
**Solution**: Add appropriate indexes based on query patterns

### 3. Cache Stampede
**Symptom**: Multiple processes rebuilding same cache
**Solution**: Use cache locks or pre-warming

### 4. Memory Leaks
**Symptom**: Increasing memory usage over time
**Solution**: Properly unset variables, use chunk processing

### 5. Blocking Operations
**Symptom**: Slow response times during heavy operations
**Solution**: Move to background jobs, use async processing

## Best Practices

1. Measure before you optimize.
2. Cache results that are read often, and clear the cache when the data changes.
3. Use `EXPLAIN` and query profiling to tune queries.
4. Set up alerts for slower response times.
5. Run load tests regularly as part of CI.
6. Record which optimizations you made and why.

## Tools and Resources

- **Laravel Debugbar**: Development profiling
- **Blackfire**: Production profiling
- **New Relic**: Application performance monitoring
- **DataDog**: Infrastructure and application monitoring
- **Apache Bench**: Simple load testing
- **K6**: Advanced load testing scenarios