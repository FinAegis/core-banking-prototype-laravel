<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Domain\Performance\Aggregates\PerformanceMetricsAggregate;
use App\Domain\Performance\Services\PerformanceMonitoringService;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PerformanceMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private PerformanceMonitoringService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PerformanceMonitoringService();
    }

    public function test_can_record_performance_metric(): void
    {
        $this->service->recordMetric(
            'api',
            'response_time',
            250.5,
            'timing',
            ['endpoint' => '/api/users']
        );

        $this->assertDatabaseHas('performance_metrics', [
            'system_id' => 'api',
            'name'      => 'response_time',
            'value'     => 250.5,
            'type'      => 'timing',
        ]);
    }

    public function test_can_record_response_time(): void
    {
        $this->service->recordResponseTime('/api/accounts', 145.3, ['method' => 'GET']);

        $this->assertDatabaseHas('performance_metrics', [
            'system_id' => 'api',
            'name'      => 'response_time',
            'value'     => 145.3,
            'type'      => 'timing',
        ]);

        // Check cache update
        $cached = Cache::get('metric:response_time:values', []);
        $this->assertContains(145.3, $cached);
    }

    public function test_triggers_alert_for_high_memory_usage(): void
    {
        $this->service->recordMemoryUsage(95.5);

        $this->assertDatabaseHas('performance_metrics', [
            'system_id' => 'system',
            'name'      => 'memory_usage',
            'value'     => 95.5,
        ]);

        // Should trigger critical alert
        $this->assertDatabaseHas('performance_alerts', [
            'system_id'  => 'system',
            'alert_type' => 'high_memory_usage',
            'severity'   => 'critical',
        ]);
    }

    public function test_can_record_database_query_performance(): void
    {
        $this->service->recordDatabaseQuery('SELECT * FROM users', 12.5);

        $this->assertDatabaseHas('performance_metrics', [
            'system_id' => 'database',
            'name'      => 'query_duration',
            'value'     => 12.5,
            'type'      => 'timing',
        ]);
    }

    public function test_can_record_cache_performance(): void
    {
        $this->service->recordCachePerformance(true, 'user:123', 0.5);

        $this->assertDatabaseHas('performance_metrics', [
            'system_id' => 'cache',
            'name'      => 'cache_hit',
            'value'     => 1,
            'type'      => 'counter',
        ]);

        $this->assertDatabaseHas('performance_metrics', [
            'system_id' => 'cache',
            'name'      => 'cache_operation_duration',
            'value'     => 0.5,
            'type'      => 'timing',
        ]);
    }

    public function test_can_record_transaction_performance(): void
    {
        $this->service->recordTransactionPerformance(
            'payment',
            1250.5,
            true,
            ['amount' => 100.00]
        );

        $this->assertDatabaseHas('performance_metrics', [
            'system_id' => 'transaction',
            'name'      => 'payment_duration',
            'value'     => 1250.5,
            'type'      => 'timing',
        ]);

        // Record failed transaction
        $this->service->recordTransactionPerformance(
            'payment',
            500.0,
            false,
            ['error' => 'insufficient_funds']
        );

        $this->assertDatabaseHas('performance_metrics', [
            'system_id' => 'transaction',
            'name'      => 'payment_errors',
            'value'     => 1,
            'type'      => 'counter',
        ]);
    }

    public function test_can_generate_performance_report(): void
    {
        // Record some metrics
        $this->service->recordMetric('api', 'response_time', 100, 'timing');
        $this->service->recordMetric('api', 'response_time', 200, 'timing');
        $this->service->recordMetric('api', 'response_time', 150, 'timing');

        $fromDate = new DateTimeImmutable('-1 hour');
        $toDate = new DateTimeImmutable();

        $report = $this->service->generateReport('api', 'hourly', $fromDate, $toDate);

        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('metrics', $report);
        $this->assertArrayHasKey('response_time', $report['metrics']);

        $responseTimeMetrics = $report['metrics']['response_time'];
        $this->assertEquals(3, $responseTimeMetrics['count']);
        $this->assertEquals(150, $responseTimeMetrics['average']);
        $this->assertEquals(100, $responseTimeMetrics['min']);
        $this->assertEquals(200, $responseTimeMetrics['max']);
    }

    public function test_can_get_dashboard_data(): void
    {
        // Set up some cache data
        Cache::put('metric:response_time:avg', 250.5, 300);
        Cache::put('metric:error_rate', 2.5, 300);
        Cache::put('metric:rpm', 120, 300);
        Cache::put('metric:active_users', 50, 300);
        Cache::put('metric:cache_hit_ratio', 85.5, 300);

        $dashboard = $this->service->getDashboardData();

        $this->assertArrayHasKey('current_metrics', $dashboard);
        $this->assertArrayHasKey('recent_alerts', $dashboard);
        $this->assertArrayHasKey('system_health', $dashboard);
        $this->assertArrayHasKey('trends', $dashboard);
        $this->assertArrayHasKey('resource_usage', $dashboard);

        $this->assertEquals(250.5, $dashboard['current_metrics']['avg_response_time']);
        $this->assertEquals(2.5, $dashboard['current_metrics']['error_rate']);
        $this->assertEquals(120, $dashboard['current_metrics']['requests_per_minute']);
    }

    public function test_performance_aggregate_calculates_statistics(): void
    {
        $aggregateUuid = 'test-performance';
        $aggregate = PerformanceMetricsAggregate::retrieve($aggregateUuid);

        // Record multiple metrics
        for ($i = 0; $i < 10; $i++) {
            $aggregate->recordMetric(
                'test',
                'test_metric',
                100 + ($i * 10),
                'gauge'
            );
        }

        $aggregate->persist();

        // Retrieve and check statistics
        $aggregate = PerformanceMetricsAggregate::retrieve($aggregateUuid);
        $stats = $aggregate->getStatistics();

        $this->assertArrayHasKey('test_metric', $stats);
        $this->assertEquals(10, $stats['test_metric']['count']);
        $this->assertEquals(100, $stats['test_metric']['min']);
        $this->assertEquals(190, $stats['test_metric']['max']);
        $this->assertEquals(145, $stats['test_metric']['avg']);
    }

    public function test_performance_aggregate_handles_thresholds(): void
    {
        $aggregateUuid = 'test-threshold';
        $aggregate = PerformanceMetricsAggregate::retrieve($aggregateUuid);

        // Set threshold
        $aggregate->setThreshold('cpu_usage', 80, 'gt', 'warning');

        // Record metric that exceeds threshold
        $aggregate->recordMetric(
            'system',
            'cpu_usage',
            95,
            'gauge'
        );

        $aggregate->persist();

        // Check alerts were created
        $aggregate = PerformanceMetricsAggregate::retrieve($aggregateUuid);
        $alerts = $aggregate->getAlerts();

        $this->assertNotEmpty($alerts);
        $this->assertEquals('cpu_usage', $alerts[0]['metric']);
        $this->assertEquals(95, $alerts[0]['value']);
        $this->assertEquals(80, $alerts[0]['threshold']);
        $this->assertEquals('warning', $alerts[0]['severity']);
    }

    public function test_can_calculate_percentiles(): void
    {
        $aggregateUuid = 'test-percentiles';
        $aggregate = PerformanceMetricsAggregate::retrieve($aggregateUuid);

        // Record 100 metrics with known distribution
        for ($i = 1; $i <= 100; $i++) {
            $aggregate->recordMetric(
                'test',
                'latency',
                $i,
                'timing'
            );
        }

        $aggregate->persist();

        // Retrieve and check percentiles
        $aggregate = PerformanceMetricsAggregate::retrieve($aggregateUuid);

        $p50 = $aggregate->getMetricPercentile('latency', 50);
        $p90 = $aggregate->getMetricPercentile('latency', 90);
        $p99 = $aggregate->getMetricPercentile('latency', 99);

        $this->assertEquals(50, $p50);
        $this->assertEquals(90, $p90);
        $this->assertEquals(99, $p99);
    }

    public function test_system_health_calculation(): void
    {
        // Set up different health scenarios
        Cache::put('metric:response_time:avg', 500, 300);  // Good
        Cache::put('metric:error_rate', 1, 300);           // Good
        Cache::put('metric:memory_usage', 60, 300);        // Good
        Cache::put('metric:cache_hit_ratio', 80, 300);     // Good

        $dashboard = $this->service->getDashboardData();
        $health = $dashboard['system_health'];

        $this->assertEquals(100, $health['score']);
        $this->assertEquals('healthy', $health['status']);
        $this->assertEmpty($health['issues']);

        // Set up degraded health
        Cache::put('metric:response_time:avg', 1500, 300); // Bad
        Cache::put('metric:error_rate', 10, 300);          // Bad

        Cache::forget('performance_dashboard'); // Clear cache
        $dashboard = $this->service->getDashboardData();
        $health = $dashboard['system_health'];

        $this->assertLessThan(100, $health['score']);
        $this->assertContains('High response time', $health['issues']);
        $this->assertContains('High error rate', $health['issues']);
    }
}
