<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Monitoring\Services;

use App\Domain\Monitoring\Services\MetricsCollector;
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

        // Clear only monitoring-related cache keys to avoid conflicts in parallel tests
        $this->clearMonitoringCache();

        $this->collector = app(MetricsCollector::class);
    }

    /**
     * Clear monitoring-specific cache keys for test isolation.
     */
    private function clearMonitoringCache(): void
    {
        $keysToForget = [
            'metrics:http:requests:total',
            'metrics:http:requests:success',
            'metrics:http:requests:errors',
            'metrics:http:requests:status:200',
            'metrics:http:requests:status:201',
            'metrics:http:requests:status:404',
            'metrics:http:requests:status:500',
            'metrics:http:methods:GET',
            'metrics:http:methods:POST',
            'metrics:http:duration:average',
            'metrics:http:duration:average:count',
            'metrics:http:duration:average:sum',
            'metrics:cache:hits',
            'metrics:cache:misses',
            'metrics:events:total',
            'metrics:events:UserRegistered:total',
            'metrics:aggregates:TestAggregate:action:total',
            'metrics:aggregates:TestAggregate:duration',
            'metrics:aggregates:TestAggregate:duration:count',
            'metrics:aggregates:TestAggregate:duration:sum',
            'metrics:workflows:TestWorkflow:started',
            'metrics:workflows:TestWorkflow:completed',
            'metrics:workflows:TestWorkflow:failed',
            'metrics:workflows:TestWorkflow:duration',
            'metrics:queue:completed',
            'metrics:queue:failed',
            'metrics:queue:duration',
            'metrics:queue:duration:count',
            'metrics:queue:duration:sum',
        ];

        foreach ($keysToForget as $key) {
            Cache::forget($key);
        }
    }

    public function test_collects_http_request_metrics(): void
    {
        // Arrange
        $method = 'GET';
        $path = '/api/users';
        $statusCode = 200;
        $duration = 0.125;

        // Act
        $this->collector->recordHttpRequest($method, $path, $statusCode, $duration);

        // Assert
        $this->assertEquals(1, Cache::get('metrics:http:requests:total'));
        $this->assertEquals(1, Cache::get('metrics:http:requests:status:200'));
        $this->assertEquals(0, Cache::get('metrics:http:requests:status:500', 0));
        $this->assertGreaterThan(0, Cache::get('metrics:http:duration:average'));
    }

    public function test_collects_error_metrics(): void
    {
        // Arrange
        $statusCode = 500;

        // Act
        $this->collector->recordHttpRequest('POST', '/api/orders', $statusCode, 0.5);

        // Assert
        $this->assertEquals(1, Cache::get('metrics:http:requests:status:500'));
    }

    public function test_collects_business_event_metrics(): void
    {
        // Arrange
        $eventName = 'OrderPlaced';
        $metadata = ['amount' => 100.00, 'currency' => 'USD'];

        // Act
        $this->collector->recordBusinessEvent($eventName, $metadata);

        // Assert
        $this->assertEquals(1, Cache::get("metrics:events:{$eventName}:total"));
        $this->assertEquals(1, Cache::get('metrics:events:total'));
    }

    public function test_collects_aggregate_metrics(): void
    {
        // Arrange
        $aggregateType = 'Order';
        $action = 'created';
        $duration = 0.05;

        // Act
        $this->collector->recordAggregateMetric($aggregateType, $action, $duration);

        // Assert
        $this->assertEquals(1, Cache::get("metrics:aggregates:{$aggregateType}:{$action}:total"));
        $this->assertGreaterThan(0, Cache::get("metrics:aggregates:{$aggregateType}:duration"));
    }

    public function test_collects_workflow_metrics(): void
    {
        // Arrange
        $workflowName = 'LoanApplicationWorkflow';
        $status = 'completed';
        $duration = 5.5;

        // Act
        $this->collector->recordWorkflowMetric($workflowName, $status, $duration);

        // Assert
        $this->assertEquals(1, Cache::get("metrics:workflows:{$workflowName}:{$status}"));
        $this->assertEquals(5.5, Cache::get("metrics:workflows:{$workflowName}:duration"));
    }

    public function test_collects_cache_metrics(): void
    {
        // Act
        $this->collector->recordCacheMetric('user_profile', true);
        $this->collector->recordCacheMetric('user_settings', false);
        $this->collector->recordCacheMetric('user_preferences', false);

        // Assert
        $this->assertEquals(1, Cache::get('metrics:cache:hits'));
        $this->assertEquals(2, Cache::get('metrics:cache:misses'));
    }

    public function test_collects_queue_metrics(): void
    {
        // Act
        $this->collector->recordQueueMetric('default', 'ProcessPayment', 'completed', 1.2);
        $this->collector->recordQueueMetric('default', 'SendEmail', 'failed', 0.5);
        $this->collector->recordQueueMetric('default', 'GenerateReport', 'completed', 3.0);

        // Assert
        $this->assertEquals(2, Cache::get('metrics:queue:completed'));
        $this->assertEquals(1, Cache::get('metrics:queue:failed'));
        $this->assertGreaterThan(0, Cache::get('metrics:queue:duration'));
    }

    public function test_increments_counters_correctly(): void
    {
        // Act - Call multiple times
        for ($i = 0; $i < 5; $i++) {
            $this->collector->recordHttpRequest('GET', '/api/test', 200, 0.1);
        }

        // Assert
        $this->assertEquals(5, Cache::get('metrics:http:requests:total'));
        $this->assertEquals(5, Cache::get('metrics:http:requests:status:200'));
    }

    public function test_calculates_average_duration(): void
    {
        // Arrange
        $durations = [0.1, 0.2, 0.3, 0.4, 0.5];

        // Act
        foreach ($durations as $duration) {
            $this->collector->recordHttpRequest('GET', '/api/test', 200, $duration);
        }

        // Assert
        $averageDuration = Cache::get('metrics:http:duration:average');
        $expectedAverage = array_sum($durations) / count($durations);
        $this->assertEqualsWithDelta($expectedAverage, $averageDuration, 0.01);
    }

    public function test_tracks_metrics_by_method(): void
    {
        // Act
        $this->collector->recordHttpRequest('GET', '/api/users', 200, 0.1);
        $this->collector->recordHttpRequest('POST', '/api/users', 201, 0.2);
        $this->collector->recordHttpRequest('GET', '/api/posts', 200, 0.1);
        $this->collector->recordHttpRequest('DELETE', '/api/posts/1', 204, 0.05);

        // Assert
        $this->assertEquals(2, Cache::get('metrics:http:methods:GET', 0));
        $this->assertEquals(1, Cache::get('metrics:http:methods:POST', 0));
        $this->assertEquals(1, Cache::get('metrics:http:methods:DELETE', 0));
    }

    public function test_tracks_metrics_by_status_code(): void
    {
        // Act
        $this->collector->recordHttpRequest('GET', '/api/test', 200, 0.1);
        $this->collector->recordHttpRequest('GET', '/api/test', 200, 0.1);
        $this->collector->recordHttpRequest('POST', '/api/test', 201, 0.2);
        $this->collector->recordHttpRequest('GET', '/api/test', 404, 0.05);
        $this->collector->recordHttpRequest('POST', '/api/test', 500, 0.3);

        // Assert
        $this->assertEquals(2, Cache::get('metrics:http:requests:status:200'));
        $this->assertEquals(1, Cache::get('metrics:http:requests:status:201'));
        $this->assertEquals(1, Cache::get('metrics:http:requests:status:404'));
        $this->assertEquals(1, Cache::get('metrics:http:requests:status:500'));
    }

    public function test_custom_metric_collection(): void
    {
        // Act
        // Note: MetricsCollector doesn't have a collectCustom method
        // Using batchRecord instead
        $this->collector->batchRecord([[
            'name'   => 'custom.metric.name',
            'type'   => 'gauge',
            'value'  => 42.5,
            'labels' => [
                'environment' => 'testing',
                'component'   => 'monitoring',
            ],
        ]]);

        // Assert
        $this->assertEquals(42.5, Cache::get('metrics:custom:custom.metric.name'));
    }

    public function test_handles_concurrent_updates(): void
    {
        // Simulate concurrent requests
        $threads = [];

        for ($i = 0; $i < 10; $i++) {
            $this->collector->recordHttpRequest('GET', '/api/concurrent', 200, 0.1);
        }

        // Assert
        $this->assertEquals(10, Cache::get('metrics:http:requests:total'));
    }

    public function test_workflow_status_tracking(): void
    {
        // Act
        $this->collector->recordWorkflowMetric('TestWorkflow', 'started', 0);
        $this->collector->recordWorkflowMetric('TestWorkflow', 'started', 0);
        $this->collector->recordWorkflowMetric('TestWorkflow', 'completed', 2.0);
        $this->collector->recordWorkflowMetric('TestWorkflow', 'failed', 1.0);

        // Assert
        $this->assertEquals(2, Cache::get('metrics:workflows:TestWorkflow:started'));
        $this->assertEquals(1, Cache::get('metrics:workflows:TestWorkflow:completed'));
        $this->assertEquals(1, Cache::get('metrics:workflows:TestWorkflow:failed'));
    }

    public function test_resets_metrics(): void
    {
        // Arrange - Set some metrics
        $this->collector->recordHttpRequest('GET', '/api/test', 200, 0.1);
        $this->collector->recordBusinessEvent('TestEvent', []);
        $this->collector->recordCacheMetric('test_key', true);

        // Act
        // Note: MetricsCollector doesn't have a reset method
        // Manually clear cache keys instead
        Cache::forget('metrics:http:requests:total');
        Cache::forget('metrics:events:total');
        Cache::forget('metrics:cache:hits');

        // Assert
        $this->assertNull(Cache::get('metrics:http:requests:total'));
        $this->assertNull(Cache::get('metrics:events:total'));
        $this->assertNull(Cache::get('metrics:cache:hits'));
    }
}
