<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\EventSourcing;

use App\Domain\Shared\EventSourcing\StreamHealthService;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class StreamHealthServiceTest extends TestCase
{
    private StreamHealthService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('event-streaming.prefix', 'test:events');
        config()->set('event-streaming.consumer_group', 'test-consumers');
        config()->set('event-streaming.backpressure.warning_threshold', 1000);
        config()->set('event-streaming.backpressure.critical_threshold', 5000);
        config()->set('event-streaming.streams', [
            'account' => 'account-events',
            'wallet'  => 'wallet-events',
        ]);

        $this->service = new StreamHealthService();
    }

    public function test_get_health_report_returns_overall_healthy(): void
    {
        // Account stream
        Redis::shouldReceive('xlen')
            ->with('test:events:account-events')
            ->andReturn(500);
        Redis::shouldReceive('xinfo')
            ->with('STREAM', 'test:events:account-events')
            ->andReturn(['first-entry' => '1-0', 'last-entry' => '500-0']);
        Redis::shouldReceive('xinfo')
            ->with('GROUPS', 'test:events:account-events')
            ->andReturn([
                ['name' => 'test-consumers', 'pending' => 10, 'consumers' => 2, 'last-delivered-id' => '500-0'],
            ]);
        Redis::shouldReceive('get')
            ->with('test:events:throughput:account')
            ->andReturn(null);
        Redis::shouldReceive('setex')
            ->withAnyArgs()
            ->andReturn(true);

        // Wallet stream
        Redis::shouldReceive('xlen')
            ->with('test:events:wallet-events')
            ->andReturn(300);
        Redis::shouldReceive('xinfo')
            ->with('STREAM', 'test:events:wallet-events')
            ->andReturn(['first-entry' => '1-0', 'last-entry' => '300-0']);
        Redis::shouldReceive('xinfo')
            ->with('GROUPS', 'test:events:wallet-events')
            ->andReturn([
                ['name' => 'test-consumers', 'pending' => 5, 'consumers' => 1, 'last-delivered-id' => '300-0'],
            ]);
        Redis::shouldReceive('get')
            ->with('test:events:throughput:wallet')
            ->andReturn(null);

        $report = $this->service->getHealthReport();

        $this->assertEquals('healthy', $report['overall_status']);
        $this->assertArrayHasKey('streams', $report);
        $this->assertArrayHasKey('account', $report['streams']);
        $this->assertArrayHasKey('wallet', $report['streams']);
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('generated_at', $report);
        $this->assertEquals(2, $report['summary']['healthy']);
    }

    public function test_get_health_report_returns_critical_when_stream_critical(): void
    {
        // Account stream - critical
        Redis::shouldReceive('xlen')
            ->with('test:events:account-events')
            ->andReturn(50000);
        Redis::shouldReceive('xinfo')
            ->with('STREAM', 'test:events:account-events')
            ->andReturn(['first-entry' => '1-0', 'last-entry' => '50000-0']);
        Redis::shouldReceive('xinfo')
            ->with('GROUPS', 'test:events:account-events')
            ->andReturn([
                ['name' => 'test-consumers', 'pending' => 6000, 'consumers' => 2, 'last-delivered-id' => '44000-0'],
            ]);
        Redis::shouldReceive('get')
            ->with('test:events:throughput:account')
            ->andReturn(null);
        Redis::shouldReceive('setex')
            ->withAnyArgs()
            ->andReturn(true);

        // Wallet stream - healthy
        Redis::shouldReceive('xlen')
            ->with('test:events:wallet-events')
            ->andReturn(300);
        Redis::shouldReceive('xinfo')
            ->with('STREAM', 'test:events:wallet-events')
            ->andReturn(['first-entry' => '1-0', 'last-entry' => '300-0']);
        Redis::shouldReceive('xinfo')
            ->with('GROUPS', 'test:events:wallet-events')
            ->andReturn([
                ['name' => 'test-consumers', 'pending' => 5, 'consumers' => 1, 'last-delivered-id' => '300-0'],
            ]);
        Redis::shouldReceive('get')
            ->with('test:events:throughput:wallet')
            ->andReturn(null);

        $report = $this->service->getHealthReport();

        $this->assertEquals('critical', $report['overall_status']);
        $this->assertEquals(1, $report['summary']['critical']);
        $this->assertEquals(1, $report['summary']['healthy']);
    }

    public function test_get_stream_metrics_returns_detailed_data(): void
    {
        Redis::shouldReceive('xlen')
            ->once()
            ->with('test:events:account-events')
            ->andReturn(1500);

        Redis::shouldReceive('xinfo')
            ->once()
            ->with('STREAM', 'test:events:account-events')
            ->andReturn([
                'first-entry' => ['1-0', ['domain' => 'account']],
                'last-entry'  => ['1500-0', ['domain' => 'account']],
            ]);

        Redis::shouldReceive('xinfo')
            ->with('GROUPS', 'test:events:account-events')
            ->andReturn([
                ['name' => 'test-consumers', 'pending' => 50, 'consumers' => 3, 'last-delivered-id' => '1450-0'],
            ]);

        Redis::shouldReceive('get')
            ->once()
            ->with('test:events:throughput:account')
            ->andReturn(null);

        Redis::shouldReceive('setex')
            ->once()
            ->andReturn(true);

        $metrics = $this->service->getStreamMetrics('account');

        $this->assertEquals('test:events:account-events', $metrics['stream_key']);
        $this->assertEquals(1500, $metrics['length']);
        $this->assertEquals('healthy', $metrics['status']);
        $this->assertEquals(50, $metrics['pending_total']);
        $this->assertNotNull($metrics['first_entry']);
        $this->assertNotNull($metrics['last_entry']);
        $this->assertIsArray($metrics['consumer_groups']);
        $this->assertCount(1, $metrics['consumer_groups']);
    }

    public function test_get_stream_metrics_returns_unavailable_on_failure(): void
    {
        Redis::shouldReceive('xlen')
            ->once()
            ->andThrow(new RuntimeException('Redis error'));

        $metrics = $this->service->getStreamMetrics('account');

        $this->assertEquals('unavailable', $metrics['status']);
        $this->assertEquals(0, $metrics['length']);
        $this->assertEquals(0, $metrics['pending_total']);
        $this->assertEmpty($metrics['consumer_groups']);
        $this->assertEquals(0.0, $metrics['throughput']);
    }

    public function test_get_consumer_metrics_returns_group_details(): void
    {
        Redis::shouldReceive('xinfo')
            ->once()
            ->with('GROUPS', 'test:events:account-events')
            ->andReturn([
                ['name' => 'test-consumers', 'pending' => 100, 'consumers' => 3, 'last-delivered-id' => '500-0'],
                ['name' => 'analytics-consumers', 'pending' => 2000, 'consumers' => 1, 'last-delivered-id' => '300-0'],
            ]);

        $metrics = $this->service->getConsumerMetrics('account');

        $this->assertCount(2, $metrics);

        $this->assertEquals('test-consumers', $metrics[0]['name']);
        $this->assertEquals(3, $metrics[0]['consumers']);
        $this->assertEquals(100, $metrics[0]['pending']);
        $this->assertEquals('healthy', $metrics[0]['status']);

        $this->assertEquals('analytics-consumers', $metrics[1]['name']);
        $this->assertEquals(1, $metrics[1]['consumers']);
        $this->assertEquals(2000, $metrics[1]['pending']);
        $this->assertEquals('warning', $metrics[1]['status']);
    }

    public function test_get_consumer_metrics_returns_critical_status(): void
    {
        Redis::shouldReceive('xinfo')
            ->once()
            ->with('GROUPS', 'test:events:account-events')
            ->andReturn([
                ['name' => 'test-consumers', 'pending' => 7000, 'consumers' => 1, 'last-delivered-id' => '100-0'],
            ]);

        $metrics = $this->service->getConsumerMetrics('account');

        $this->assertCount(1, $metrics);
        $this->assertEquals('critical', $metrics[0]['status']);
        $this->assertEquals(7000, $metrics[0]['pending']);
    }

    public function test_get_consumer_metrics_returns_empty_on_failure(): void
    {
        Redis::shouldReceive('xinfo')
            ->once()
            ->andThrow(new RuntimeException('Redis error'));

        $metrics = $this->service->getConsumerMetrics('account');

        $this->assertEmpty($metrics);
    }

    public function test_get_health_report_handles_unavailable_streams(): void
    {
        // Account stream - unavailable
        Redis::shouldReceive('xlen')
            ->with('test:events:account-events')
            ->andThrow(new RuntimeException('Stream not found'));

        // Wallet stream - healthy
        Redis::shouldReceive('xlen')
            ->with('test:events:wallet-events')
            ->andReturn(100);
        Redis::shouldReceive('xinfo')
            ->with('STREAM', 'test:events:wallet-events')
            ->andReturn(['first-entry' => '1-0', 'last-entry' => '100-0']);
        Redis::shouldReceive('xinfo')
            ->with('GROUPS', 'test:events:wallet-events')
            ->andReturn([
                ['name' => 'test-consumers', 'pending' => 0, 'consumers' => 1, 'last-delivered-id' => '100-0'],
            ]);
        Redis::shouldReceive('get')
            ->with('test:events:throughput:wallet')
            ->andReturn(null);
        Redis::shouldReceive('setex')
            ->withAnyArgs()
            ->andReturn(true);

        $report = $this->service->getHealthReport();

        $this->assertEquals('degraded', $report['overall_status']);
        $this->assertEquals(1, $report['summary']['unavailable']);
        $this->assertEquals(1, $report['summary']['healthy']);
    }

    public function test_get_stream_metrics_calculates_throughput_with_previous_data(): void
    {
        $previousTime = microtime(true) - 10; // 10 seconds ago
        $previousLength = 1000;

        Redis::shouldReceive('xlen')
            ->once()
            ->with('test:events:account-events')
            ->andReturn(1100); // 100 new messages

        Redis::shouldReceive('xinfo')
            ->once()
            ->with('STREAM', 'test:events:account-events')
            ->andReturn(['first-entry' => '1-0', 'last-entry' => '1100-0']);

        Redis::shouldReceive('xinfo')
            ->with('GROUPS', 'test:events:account-events')
            ->andReturn([
                ['name' => 'test-consumers', 'pending' => 0, 'consumers' => 1, 'last-delivered-id' => '1100-0'],
            ]);

        Redis::shouldReceive('get')
            ->once()
            ->with('test:events:throughput:account')
            ->andReturn(json_encode(['length' => $previousLength, 'time' => $previousTime]));

        Redis::shouldReceive('setex')
            ->once()
            ->andReturn(true);

        $metrics = $this->service->getStreamMetrics('account');

        $this->assertGreaterThan(0, $metrics['throughput']);
        // ~100 messages / ~10 seconds = ~10 msg/s
        $this->assertGreaterThan(5.0, $metrics['throughput']);
        $this->assertLessThan(20.0, $metrics['throughput']);
    }

    public function test_get_stream_metrics_for_unknown_domain_uses_fallback_key(): void
    {
        Redis::shouldReceive('xlen')
            ->once()
            ->with('test:events:custom-domain-events')
            ->andReturn(0);

        Redis::shouldReceive('xinfo')
            ->once()
            ->with('STREAM', 'test:events:custom-domain-events')
            ->andReturn(false);

        Redis::shouldReceive('xinfo')
            ->with('GROUPS', 'test:events:custom-domain-events')
            ->andReturn([]);

        Redis::shouldReceive('get')
            ->once()
            ->with('test:events:throughput:custom-domain')
            ->andReturn(null);

        Redis::shouldReceive('setex')
            ->once()
            ->andReturn(true);

        $metrics = $this->service->getStreamMetrics('custom-domain');

        $this->assertEquals('test:events:custom-domain-events', $metrics['stream_key']);
        $this->assertEquals(0, $metrics['length']);
        $this->assertEquals('healthy', $metrics['status']);
    }
}
