<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Performance;

use App\Domain\Performance\Models\PerformanceMetric;
use App\Domain\Performance\Services\MetricsCollectorService;
use App\Domain\Performance\ValueObjects\MetricType;
use App\Domain\Performance\ValueObjects\PerformanceThreshold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsCollectorTest extends TestCase
{
    use RefreshDatabase;

    private MetricsCollectorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MetricsCollectorService();
    }

    public function test_can_record_metric(): void
    {
        $this->service->recordMetric(
            'test.metric',
            100.5,
            MetricType::GAUGE,
            ['tag' => 'value']
        );

        $metric = PerformanceMetric::where('name', 'test.metric')->first();

        $this->assertNotNull($metric);
        $this->assertEquals(100.5, $metric->value);
        $this->assertEquals('gauge', $metric->type);
        $this->assertEquals(['tag' => 'value'], $metric->tags);
    }

    public function test_can_record_response_time(): void
    {
        $this->service->recordResponseTime('/api/users', 250.5, ['method' => 'GET']);

        $metric = PerformanceMetric::where('name', 'response_time./api/users')->first();

        $this->assertNotNull($metric);
        $this->assertEquals(250.5, $metric->value);
        $this->assertEquals('latency', $metric->type);
        $this->assertEquals('GET', $metric->tags['method']);
    }

    public function test_can_record_throughput(): void
    {
        $this->service->recordThroughput('transactions', 1000, ['type' => 'payment']);

        $metric = PerformanceMetric::where('name', 'throughput.transactions')->first();

        $this->assertNotNull($metric);
        $this->assertEquals(1000, $metric->value);
        $this->assertEquals('throughput', $metric->type);
        $this->assertEquals('payment', $metric->tags['type']);
    }

    public function test_can_record_error_rate(): void
    {
        $this->service->recordErrorRate('api', 2.5, ['endpoint' => '/users']);

        $metric = PerformanceMetric::where('name', 'error_rate.api')->first();

        $this->assertNotNull($metric);
        $this->assertEquals(2.5, $metric->value);
        $this->assertEquals('error_rate', $metric->type);
    }

    public function test_can_get_metrics_summary(): void
    {
        // Record some metrics
        $this->service->recordMetric('cpu.usage', 50, MetricType::CPU_USAGE);
        $this->service->recordMetric('cpu.usage', 60, MetricType::CPU_USAGE);
        $this->service->recordMetric('cpu.usage', 70, MetricType::CPU_USAGE);

        $summary = $this->service->getMetricsSummary(5);

        $this->assertArrayHasKey('cpu.usage', $summary);
        $this->assertEquals(60, $summary['cpu.usage']['average']);
        $this->assertEquals(50, $summary['cpu.usage']['min']);
        $this->assertEquals(70, $summary['cpu.usage']['max']);
        $this->assertEquals(3, $summary['cpu.usage']['count']);
    }

    public function test_can_set_custom_threshold(): void
    {
        $threshold = new PerformanceThreshold(
            value: 90,
            operator: '>',
            severity: 'critical',
            triggerAlert: true
        );

        $this->service->setThreshold('custom.metric', $threshold);

        // This metric should not trigger alert (below threshold)
        $this->service->recordMetric('custom.metric', 80, MetricType::GAUGE);

        // This metric should trigger alert (above threshold)
        $this->service->recordMetric('custom.metric', 95, MetricType::GAUGE);

        $metrics = PerformanceMetric::where('name', 'custom.metric')->get();
        $this->assertCount(2, $metrics);
    }

    public function test_performance_threshold_value_object(): void
    {
        $threshold = new PerformanceThreshold(
            value: 100,
            operator: '>=',
            severity: 'warning'
        );

        $this->assertFalse($threshold->isExceeded(99));
        $this->assertTrue($threshold->isExceeded(100));
        $this->assertTrue($threshold->isExceeded(101));
        $this->assertEquals('warning', $threshold->getSeverity());
    }

    public function test_metric_type_enum(): void
    {
        $this->assertEquals('milliseconds', MetricType::LATENCY->getUnit());
        $this->assertEquals('percentage', MetricType::CPU_USAGE->getUnit());
        $this->assertEquals('bytes', MetricType::MEMORY_USAGE->getUnit());

        $this->assertTrue(MetricType::CPU_USAGE->isPercentage());
        $this->assertTrue(MetricType::LATENCY->isTime());
        $this->assertFalse(MetricType::GAUGE->isPercentage());
    }
}
