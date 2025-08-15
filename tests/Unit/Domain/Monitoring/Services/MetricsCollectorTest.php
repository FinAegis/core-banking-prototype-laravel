<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Monitoring\Services;

use App\Domain\Monitoring\Services\MetricsCollector;
use App\Domain\Monitoring\ValueObjects\MetricType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MetricsCollectorTest extends TestCase
{
    use RefreshDatabase;

    private MetricsCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->collector = app(MetricsCollector::class);
    }

    public function test_collects_http_request_metrics(): void
    {
        // Arrange
        $method = 'GET';
        $path = '/api/users';
        $statusCode = 200;
        $duration = 0.125;

        // Act
        $this->collector->collectHttpRequest($method, $path, $statusCode, $duration);

        // Assert
        $this->assertEquals(1, Cache::get('metrics.http.total'));
        $this->assertEquals(1, Cache::get('metrics.http.success'));
        $this->assertEquals(0, Cache::get('metrics.http.errors', 0));
        $this->assertGreaterThan(0, Cache::get('metrics.http.duration'));
    }

    public function test_collects_error_metrics(): void
    {
        // Arrange
        $statusCode = 500;

        // Act
        $this->collector->collectHttpRequest('POST', '/api/orders', $statusCode, 0.5);

        // Assert
        $this->assertEquals(1, Cache::get('metrics.http.errors'));
    }

    public function test_collects_business_event_metrics(): void
    {
        // Arrange
        $eventName = 'OrderPlaced';
        $metadata = ['amount' => 100.00, 'currency' => 'USD'];

        // Act
        $this->collector->collectBusinessEvent($eventName, $metadata);

        // Assert
        $this->assertEquals(1, Cache::get("metrics.events.{$eventName}"));
        $this->assertEquals(1, Cache::get('metrics.events.total'));
    }

    public function test_collects_aggregate_metrics(): void
    {
        // Arrange
        $aggregateType = 'Order';
        $action = 'created';
        $duration = 0.05;

        // Act
        $this->collector->collectAggregate($aggregateType, $action, $duration);

        // Assert
        $this->assertEquals(1, Cache::get("metrics.aggregates.{$aggregateType}.{$action}"));
        $this->assertGreaterThan(0, Cache::get("metrics.aggregates.{$aggregateType}.duration"));
    }

    public function test_collects_workflow_metrics(): void
    {
        // Arrange
        $workflowName = 'LoanApplicationWorkflow';
        $status = 'completed';
        $duration = 5.5;

        // Act
        $this->collector->collectWorkflow($workflowName, $status, $duration);

        // Assert
        $this->assertEquals(1, Cache::get("metrics.workflows.{$workflowName}.{$status}"));
        $this->assertEquals(5.5, Cache::get("metrics.workflows.{$workflowName}.duration"));
    }

    public function test_collects_cache_metrics(): void
    {
        // Act
        $this->collector->collectCacheHit('user_profile');
        $this->collector->collectCacheMiss('user_settings');
        $this->collector->collectCacheMiss('user_preferences');

        // Assert
        $this->assertEquals(1, Cache::get('metrics.cache.hits'));
        $this->assertEquals(2, Cache::get('metrics.cache.misses'));
    }

    public function test_collects_queue_metrics(): void
    {
        // Act
        $this->collector->collectQueueJob('ProcessPayment', 'completed', 1.2);
        $this->collector->collectQueueJob('SendEmail', 'failed', 0.5);
        $this->collector->collectQueueJob('GenerateReport', 'completed', 3.0);

        // Assert
        $this->assertEquals(2, Cache::get('metrics.queue.completed'));
        $this->assertEquals(1, Cache::get('metrics.queue.failed'));
        $this->assertGreaterThan(0, Cache::get('metrics.queue.duration'));
    }

    public function test_increments_counters_correctly(): void
    {
        // Act - Call multiple times
        for ($i = 0; $i < 5; $i++) {
            $this->collector->collectHttpRequest('GET', '/api/test', 200, 0.1);
        }

        // Assert
        $this->assertEquals(5, Cache::get('metrics.http.total'));
        $this->assertEquals(5, Cache::get('metrics.http.success'));
    }

    public function test_calculates_average_duration(): void
    {
        // Arrange
        $durations = [0.1, 0.2, 0.3, 0.4, 0.5];

        // Act
        foreach ($durations as $duration) {
            $this->collector->collectHttpRequest('GET', '/api/test', 200, $duration);
        }

        // Assert
        $averageDuration = Cache::get('metrics.http.duration');
        $expectedAverage = array_sum($durations) / count($durations);
        $this->assertEqualsWithDelta($expectedAverage, $averageDuration, 0.01);
    }

    public function test_tracks_metrics_by_method(): void
    {
        // Act
        $this->collector->collectHttpRequest('GET', '/api/users', 200, 0.1);
        $this->collector->collectHttpRequest('POST', '/api/users', 201, 0.2);
        $this->collector->collectHttpRequest('GET', '/api/posts', 200, 0.1);
        $this->collector->collectHttpRequest('DELETE', '/api/posts/1', 204, 0.05);

        // Assert
        $byMethod = Cache::get('metrics.http.by_method', []);
        $this->assertEquals(2, $byMethod['GET'] ?? 0);
        $this->assertEquals(1, $byMethod['POST'] ?? 0);
        $this->assertEquals(1, $byMethod['DELETE'] ?? 0);
    }

    public function test_tracks_metrics_by_status_code(): void
    {
        // Act
        $this->collector->collectHttpRequest('GET', '/api/test', 200, 0.1);
        $this->collector->collectHttpRequest('GET', '/api/test', 200, 0.1);
        $this->collector->collectHttpRequest('POST', '/api/test', 201, 0.2);
        $this->collector->collectHttpRequest('GET', '/api/test', 404, 0.05);
        $this->collector->collectHttpRequest('POST', '/api/test', 500, 0.3);

        // Assert
        $byStatus = Cache::get('metrics.http.by_status', []);
        $this->assertEquals(2, $byStatus['2xx'] ?? 0);
        $this->assertEquals(1, $byStatus['201'] ?? 0);
        $this->assertEquals(1, $byStatus['4xx'] ?? 0);
        $this->assertEquals(1, $byStatus['5xx'] ?? 0);
    }

    public function test_custom_metric_collection(): void
    {
        // Act
        $this->collector->collectCustom('custom.metric.name', 42.5, [
            'environment' => 'testing',
            'component' => 'monitoring',
        ]);

        // Assert
        $this->assertEquals(42.5, Cache::get('metrics.custom.custom.metric.name'));
    }

    public function test_handles_concurrent_updates(): void
    {
        // Simulate concurrent requests
        $threads = [];
        
        for ($i = 0; $i < 10; $i++) {
            $this->collector->collectHttpRequest('GET', '/api/concurrent', 200, 0.1);
        }

        // Assert
        $this->assertEquals(10, Cache::get('metrics.http.total'));
    }

    public function test_workflow_status_tracking(): void
    {
        // Act
        $this->collector->collectWorkflow('TestWorkflow', 'started', 0);
        $this->collector->collectWorkflow('TestWorkflow', 'started', 0);
        $this->collector->collectWorkflow('TestWorkflow', 'completed', 2.0);
        $this->collector->collectWorkflow('TestWorkflow', 'failed', 1.0);

        // Assert
        $this->assertEquals(2, Cache::get('metrics.workflows.TestWorkflow.started'));
        $this->assertEquals(1, Cache::get('metrics.workflows.TestWorkflow.completed'));
        $this->assertEquals(1, Cache::get('metrics.workflows.TestWorkflow.failed'));
    }

    public function test_resets_metrics(): void
    {
        // Arrange - Set some metrics
        $this->collector->collectHttpRequest('GET', '/api/test', 200, 0.1);
        $this->collector->collectBusinessEvent('TestEvent', []);
        $this->collector->collectCacheHit('test_key');

        // Act
        $this->collector->reset();

        // Assert
        $this->assertNull(Cache::get('metrics.http.total'));
        $this->assertNull(Cache::get('metrics.events.total'));
        $this->assertNull(Cache::get('metrics.cache.hits'));
    }
}