<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\EventSourcing;

use App\Domain\Shared\EventSourcing\BackpressureHandler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class BackpressureHandlerTest extends TestCase
{
    private BackpressureHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('event-streaming.prefix', 'test:events');
        config()->set('event-streaming.consumer_group', 'test-consumers');
        config()->set('event-streaming.backpressure.warning_threshold', 1000);
        config()->set('event-streaming.backpressure.critical_threshold', 5000);
        config()->set('event-streaming.backpressure.resume_threshold', 500);
        config()->set('event-streaming.streams', ['account' => 'account-events']);

        Cache::flush();

        $this->handler = new BackpressureHandler();
    }

    public function test_check_backpressure_returns_healthy_when_below_thresholds(): void
    {
        Redis::shouldReceive('xinfo')
            ->once()
            ->with('GROUPS', 'test:events:account-events')
            ->andReturn([
                ['name' => 'test-consumers', 'pending' => 500, 'consumers' => 2, 'last-delivered-id' => '100-0'],
            ]);

        $result = $this->handler->checkBackpressure('account');

        $this->assertEquals('healthy', $result['status']);
        $this->assertEquals(500, $result['pending_count']);
        $this->assertFalse($result['should_pause']);
        $this->assertEquals(1000, $result['threshold_warning']);
        $this->assertEquals(5000, $result['threshold_critical']);
    }

    public function test_check_backpressure_returns_warning_at_threshold(): void
    {
        Redis::shouldReceive('xinfo')
            ->once()
            ->with('GROUPS', 'test:events:account-events')
            ->andReturn([
                ['name' => 'test-consumers', 'pending' => 1500, 'consumers' => 2, 'last-delivered-id' => '100-0'],
            ]);

        $result = $this->handler->checkBackpressure('account');

        $this->assertEquals('warning', $result['status']);
        $this->assertEquals(1500, $result['pending_count']);
        $this->assertFalse($result['should_pause']);
    }

    public function test_check_backpressure_returns_critical_and_should_pause(): void
    {
        Redis::shouldReceive('xinfo')
            ->once()
            ->with('GROUPS', 'test:events:account-events')
            ->andReturn([
                ['name' => 'test-consumers', 'pending' => 6000, 'consumers' => 2, 'last-delivered-id' => '100-0'],
            ]);

        $result = $this->handler->checkBackpressure('account');

        $this->assertEquals('critical', $result['status']);
        $this->assertEquals(6000, $result['pending_count']);
        $this->assertTrue($result['should_pause']);
    }

    public function test_check_backpressure_should_not_pause_when_already_paused(): void
    {
        Cache::put('event-streaming:paused:account', [
            'paused_at' => now()->toIso8601String(),
            'reason'    => 'backpressure',
            'domain'    => 'account',
        ], now()->addHours(24));

        Redis::shouldReceive('xinfo')
            ->once()
            ->with('GROUPS', 'test:events:account-events')
            ->andReturn([
                ['name' => 'test-consumers', 'pending' => 6000, 'consumers' => 2, 'last-delivered-id' => '100-0'],
            ]);

        $result = $this->handler->checkBackpressure('account');

        $this->assertEquals('critical', $result['status']);
        $this->assertFalse($result['should_pause']); // Already paused
    }

    public function test_pause_consumer_stores_pause_info(): void
    {
        $result = $this->handler->pauseConsumer('account', 'manual pause');

        $this->assertTrue($result);
        $this->assertTrue($this->handler->isConsumerPaused('account'));
        $this->assertTrue(Cache::has('event-streaming:paused:account'));
    }

    public function test_resume_consumer_when_below_resume_threshold(): void
    {
        // Pause first
        Cache::put('event-streaming:paused:account', [
            'paused_at' => now()->toIso8601String(),
            'reason'    => 'backpressure',
            'domain'    => 'account',
        ], now()->addHours(24));

        Redis::shouldReceive('xinfo')
            ->once()
            ->with('GROUPS', 'test:events:account-events')
            ->andReturn([
                ['name' => 'test-consumers', 'pending' => 200, 'consumers' => 2, 'last-delivered-id' => '100-0'],
            ]);

        $result = $this->handler->resumeConsumer('account');

        $this->assertTrue($result);
        $this->assertFalse($this->handler->isConsumerPaused('account'));
    }

    public function test_resume_consumer_fails_when_above_resume_threshold(): void
    {
        // Pause first
        Cache::put('event-streaming:paused:account', [
            'paused_at' => now()->toIso8601String(),
            'reason'    => 'backpressure',
            'domain'    => 'account',
        ], now()->addHours(24));

        Redis::shouldReceive('xinfo')
            ->once()
            ->with('GROUPS', 'test:events:account-events')
            ->andReturn([
                ['name' => 'test-consumers', 'pending' => 800, 'consumers' => 2, 'last-delivered-id' => '100-0'],
            ]);

        $result = $this->handler->resumeConsumer('account');

        $this->assertFalse($result);
        $this->assertTrue($this->handler->isConsumerPaused('account'));
    }

    public function test_get_consumer_health_returns_healthy_status(): void
    {
        Redis::shouldReceive('xinfo')
            ->once()
            ->with('GROUPS', 'test:events:account-events')
            ->andReturn([
                ['name' => 'test-consumers', 'pending' => 100, 'consumers' => 3, 'last-delivered-id' => '100-0'],
            ]);

        $result = $this->handler->getConsumerHealth('account');

        $this->assertEquals('account', $result['domain']);
        $this->assertEquals('healthy', $result['status']);
        $this->assertEquals(100, $result['pending_count']);
        $this->assertFalse($result['is_paused']);
        $this->assertNull($result['pause_info']);
        $this->assertEquals('test-consumers', $result['consumer_group']);
    }

    public function test_get_consumer_health_returns_paused_status(): void
    {
        Cache::put('event-streaming:paused:account', [
            'paused_at' => '2026-01-01T00:00:00+00:00',
            'reason'    => 'backpressure',
            'domain'    => 'account',
        ], now()->addHours(24));

        Redis::shouldReceive('xinfo')
            ->once()
            ->with('GROUPS', 'test:events:account-events')
            ->andReturn([
                ['name' => 'test-consumers', 'pending' => 3000, 'consumers' => 3, 'last-delivered-id' => '100-0'],
            ]);

        $result = $this->handler->getConsumerHealth('account');

        $this->assertEquals('paused', $result['status']);
        $this->assertTrue($result['is_paused']);
        $this->assertNotNull($result['pause_info']);
        $this->assertEquals('backpressure', $result['pause_info']['reason']);
    }

    public function test_get_consumer_health_returns_critical_status(): void
    {
        Redis::shouldReceive('xinfo')
            ->once()
            ->with('GROUPS', 'test:events:account-events')
            ->andReturn([
                ['name' => 'test-consumers', 'pending' => 6000, 'consumers' => 1, 'last-delivered-id' => '100-0'],
            ]);

        $result = $this->handler->getConsumerHealth('account');

        $this->assertEquals('critical', $result['status']);
        $this->assertEquals(6000, $result['pending_count']);
    }

    public function test_is_consumer_paused_returns_false_when_not_paused(): void
    {
        $this->assertFalse($this->handler->isConsumerPaused('account'));
    }

    public function test_check_backpressure_handles_redis_failure_gracefully(): void
    {
        Redis::shouldReceive('xinfo')
            ->once()
            ->andThrow(new RuntimeException('Redis unavailable'));

        $result = $this->handler->checkBackpressure('account');

        $this->assertEquals('healthy', $result['status']);
        $this->assertEquals(0, $result['pending_count']);
        $this->assertFalse($result['should_pause']);
    }

    public function test_check_backpressure_returns_zero_when_consumer_group_not_found(): void
    {
        Redis::shouldReceive('xinfo')
            ->once()
            ->with('GROUPS', 'test:events:account-events')
            ->andReturn([
                ['name' => 'other-group', 'pending' => 9999, 'consumers' => 1, 'last-delivered-id' => '100-0'],
            ]);

        $result = $this->handler->checkBackpressure('account');

        $this->assertEquals('healthy', $result['status']);
        $this->assertEquals(0, $result['pending_count']);
    }

    public function test_pause_consumer_uses_default_reason(): void
    {
        $result = $this->handler->pauseConsumer('account');

        $this->assertTrue($result);
        $this->assertTrue(Cache::has('event-streaming:paused:account'));
    }
}
