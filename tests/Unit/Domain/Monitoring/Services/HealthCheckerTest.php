<?php

declare(strict_types=1);

use App\Domain\Monitoring\Services\HealthChecker;
use App\Domain\Monitoring\Services\MetricsCollector;
use App\Domain\Monitoring\Repositories\MonitoringAggregateRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Http;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = Mockery::mock(MonitoringAggregateRepository::class);
    $this->metricsCollector = new MetricsCollector();
    $this->healthChecker = new HealthChecker($this->repository, $this->metricsCollector);
    Cache::flush();
});

it('can check database health', function () {
    // Mock database connection
    DB::shouldReceive('select')->with('SELECT 1')->once()->andReturn([1]);
    DB::shouldReceive('connection->count')->andReturn(10);
    
    $result = $this->healthChecker->checkDatabase();
    
    expect($result)->toHaveKey('healthy');
    expect($result)->toHaveKey('status');
    expect($result)->toHaveKey('duration_ms');
    expect($result)->toHaveKey('connections');
});

it('detects database failure', function () {
    // Mock database failure
    DB::shouldReceive('select')
        ->once()
        ->andThrow(new Exception('Database connection failed'));
    
    $result = $this->healthChecker->checkDatabase();
    
    expect($result['healthy'])->toBeFalse();
    expect($result['status'])->toBe('unhealthy');
    expect($result)->toHaveKey('error');
});

it('can check redis health', function () {
    // Mock Redis ping
    Redis::shouldReceive('connection->ping')
        ->once()
        ->andReturn('PONG');
    
    $result = $this->healthChecker->checkRedis();
    
    expect($result['healthy'])->toBeTrue();
    expect($result['status'])->toBe('healthy');
});

it('detects redis failure', function () {
    // Mock Redis failure
    Redis::shouldReceive('connection->ping')
        ->once()
        ->andThrow(new Exception('Connection refused'));
    
    $result = $this->healthChecker->checkRedis();
    
    expect($result['healthy'])->toBeFalse();
    expect($result['status'])->toBe('unhealthy');
});

it('can check queue health', function () {
    // Mock queue size
    Queue::shouldReceive('size')
        ->once()
        ->with('default')
        ->andReturn(10);
    
    $result = $this->healthChecker->checkQueue();
    
    expect($result['healthy'])->toBeTrue();
    expect($result['message'])->toContain('Queue is healthy');
    expect($result['details'])->toHaveKey('default_queue_size');
    expect($result['details']['default_queue_size'])->toBe(10);
});

it('detects queue overload', function () {
    // Mock large queue size
    Queue::shouldReceive('size')
        ->once()
        ->with('default')
        ->andReturn(5001);
    
    $result = $this->healthChecker->checkQueue();
    
    expect($result['healthy'])->toBeFalse();
    expect($result['message'])->toContain('Queue is overloaded');
});

it('can check external service health', function () {
    Http::fake([
        'https://api.example.com/health' => Http::response(['status' => 'ok'], 200),
    ]);
    
    $result = $this->healthChecker->checkExternalService('https://api.example.com/health', 'Example API');
    
    expect($result['healthy'])->toBeTrue();
    expect($result['message'])->toContain('Example API is healthy');
    expect($result['details'])->toHaveKey('response_time');
});

it('detects external service failure', function () {
    Http::fake([
        'https://api.example.com/health' => Http::response(null, 503),
    ]);
    
    $result = $this->healthChecker->checkExternalService('https://api.example.com/health', 'Example API');
    
    expect($result['healthy'])->toBeFalse();
    expect($result['message'])->toContain('Example API check failed');
    expect($result['details'])->toHaveKey('status_code');
    expect($result['details']['status_code'])->toBe(503);
});

it('handles external service timeout', function () {
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
    });
    
    $result = $this->healthChecker->checkExternalService('https://api.example.com/health', 'Example API');
    
    expect($result['healthy'])->toBeFalse();
    expect($result['message'])->toContain('Example API check failed');
    expect($result['details'])->toHaveKey('error');
});

it('can run all health checks', function () {
    // Mock successful checks
    DB::shouldReceive('select')->with('SELECT 1')->andReturn([1]);
    DB::shouldReceive('connection->count')->andReturn(10);
    Cache::shouldReceive('get')->andReturn(null);
    Cache::shouldReceive('put')->andReturn(true);
    Redis::shouldReceive('connection->ping')->andReturn('PONG');
    Queue::shouldReceive('size')->andReturn(100);
    
    // Mock repository and aggregate for recording
    $aggregate = Mockery::mock(\App\Domain\Monitoring\Aggregates\MonitoringAggregate::class);
    $aggregate->shouldReceive('performHealthCheck')->andReturnSelf();
    $this->repository->shouldReceive('store')->andReturn(true);
    
    $results = $this->healthChecker->check();
    
    expect($results)->toHaveKey('status');
    expect($results)->toHaveKey('healthy');
    expect($results)->toHaveKey('checks');
    expect($results['checks'])->toHaveKey('database');
    expect($results['checks'])->toHaveKey('redis');
    expect($results['checks'])->toHaveKey('queue');
});

it('determines overall status correctly when all healthy', function () {
    // Mock all services healthy
    DB::shouldReceive('connection->getPdo')->andReturn(true);
    Redis::shouldReceive('ping')->andReturn('PONG');
    Queue::shouldReceive('size')->andReturn(100);
    
    $results = $this->healthChecker->runAllChecks();
    
    expect($results['overall_status'])->toBe('healthy');
});

it('determines overall status as degraded with one failure', function () {
    // Mock database failure, others healthy
    DB::shouldReceive('connection')
        ->once()
        ->andThrow(new Exception('Database connection failed'));
    Redis::shouldReceive('ping')->andReturn('PONG');
    Queue::shouldReceive('size')->andReturn(100);
    
    $results = $this->healthChecker->runAllChecks();
    
    expect($results['overall_status'])->toBe('degraded');
});

it('determines overall status as unhealthy with multiple failures', function () {
    // Mock multiple failures
    DB::shouldReceive('connection')
        ->once()
        ->andThrow(new Exception('Database connection failed'));
    Redis::shouldReceive('ping')
        ->once()
        ->andThrow(new Exception('Redis connection failed'));
    Queue::shouldReceive('size')->andReturn(100);
    
    $results = $this->healthChecker->runAllChecks();
    
    expect($results['overall_status'])->toBe('unhealthy');
});

it('can register custom health checks', function () {
    $customCheck = function () {
        return [
            'healthy' => true,
            'message' => 'Custom service is healthy',
            'details' => ['custom_metric' => 42],
        ];
    };
    
    $this->healthChecker->registerCheck('custom_service', $customCheck);
    
    $results = $this->healthChecker->runAllChecks();
    
    expect($results)->toHaveKey('custom_service');
    expect($results['custom_service']['healthy'])->toBeTrue();
    expect($results['custom_service']['details']['custom_metric'])->toBe(42);
});

it('handles exceptions in custom checks gracefully', function () {
    $faultyCheck = function () {
        throw new Exception('Custom check failed');
    };
    
    $this->healthChecker->registerCheck('faulty_service', $faultyCheck);
    
    $results = $this->healthChecker->runAllChecks();
    
    expect($results)->toHaveKey('faulty_service');
    expect($results['faulty_service']['healthy'])->toBeFalse();
    expect($results['faulty_service']['message'])->toContain('Health check failed');
});

it('caches health check results', function () {
    DB::shouldReceive('connection->getPdo')->once()->andReturn(true);
    Redis::shouldReceive('ping')->once()->andReturn('PONG');
    Queue::shouldReceive('size')->once()->andReturn(100);
    
    // First call should execute checks
    $results1 = $this->healthChecker->runAllChecks(true); // Enable caching
    
    // Second call should use cache (mocks are set to once())
    $results2 = $this->healthChecker->runAllChecks(true);
    
    expect($results1)->toEqual($results2);
});

it('respects cache TTL for health checks', function () {
    DB::shouldReceive('connection->getPdo')->twice()->andReturn(true);
    Redis::shouldReceive('ping')->twice()->andReturn('PONG');
    Queue::shouldReceive('size')->twice()->andReturn(100);
    
    // First call
    $this->healthChecker->runAllChecks(true, 1); // 1 second TTL
    
    // Wait for cache to expire
    sleep(2);
    
    // Second call should execute checks again
    $this->healthChecker->runAllChecks(true, 1);
    
    // Assertions handled by mock expectations (twice())
    expect(true)->toBeTrue();
});

it('can get health status summary', function () {
    DB::shouldReceive('connection->getPdo')->andReturn(true);
    Redis::shouldReceive('ping')->andReturn('PONG');
    Queue::shouldReceive('size')->andReturn(100);
    
    $summary = $this->healthChecker->getHealthSummary();
    
    expect($summary)->toHaveKey('status');
    expect($summary)->toHaveKey('healthy_components');
    expect($summary)->toHaveKey('unhealthy_components');
    expect($summary)->toHaveKey('total_components');
    expect($summary)->toHaveKey('last_check');
});

it('tracks health check history', function () {
    DB::shouldReceive('connection->getPdo')->andReturn(true);
    Redis::shouldReceive('ping')->andReturn('PONG');
    Queue::shouldReceive('size')->andReturn(100);
    
    // Run checks multiple times
    $this->healthChecker->runAllChecks();
    sleep(1);
    $this->healthChecker->runAllChecks();
    
    $history = Cache::get('health:history', []);
    
    expect($history)->toHaveCount(2);
    expect($history[0])->toHaveKey('timestamp');
    expect($history[0])->toHaveKey('overall_status');
});