<?php

declare(strict_types=1);

namespace Tests\Domain\Banking\Services;

use App\Domain\Banking\Contracts\IBankConnector;
use App\Domain\Banking\Events\BankHealthChanged;
use App\Domain\Banking\Models\BankCapabilities;
use App\Domain\Banking\Services\BankHealthMonitor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Tests\UnitTestCase;

class BankHealthMonitorTest extends UnitTestCase
{
    private BankHealthMonitor $monitor;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->monitor = new BankHealthMonitor();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ===========================================
    // registerBank Tests
    // ===========================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_registers_bank_connector(): void
    {
        $connector = $this->createMockConnector('PAYSERA');
        $this->monitor->registerBank('PAYSERA', $connector);

        // Verify it's registered by checking health
        $connector->shouldReceive('isAvailable')->andReturn(true);
        $connector->shouldReceive('getCapabilities')->andReturn(BankCapabilities::fromArray([]));
        $connector->shouldReceive('getSupportedCurrencies')->andReturn(['EUR']);

        $health = $this->monitor->checkHealth('PAYSERA');

        expect($health['status'])->toBe('healthy');
    }

    // ===========================================
    // checkHealth Tests
    // ===========================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_unknown_status_for_unregistered_bank(): void
    {
        $health = $this->monitor->checkHealth('UNKNOWN_BANK');

        expect($health['status'])->toBe('unknown');
        expect($health['message'])->toBe('Bank not registered');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_healthy_status_when_bank_is_available(): void
    {
        $connector = $this->createMockConnector('PAYSERA');
        $connector->shouldReceive('isAvailable')->once()->andReturn(true);
        $connector->shouldReceive('getCapabilities')->andReturn(BankCapabilities::fromArray([]));
        $connector->shouldReceive('getSupportedCurrencies')->andReturn(['EUR', 'USD']);

        $this->monitor->registerBank('PAYSERA', $connector);

        $health = $this->monitor->checkHealth('PAYSERA');

        expect($health['status'])->toBe('healthy');
        expect($health['available'])->toBeTrue();
        expect($health['supported_currencies'])->toContain('EUR', 'USD');
        expect($health)->toHaveKey('response_time_ms');
        expect($health)->toHaveKey('last_check');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_unhealthy_status_when_bank_is_unavailable(): void
    {
        $connector = $this->createMockConnector('PAYSERA');
        $connector->shouldReceive('isAvailable')->once()->andReturn(false);
        $connector->shouldReceive('getCapabilities')->andReturn(BankCapabilities::fromArray([]));

        $this->monitor->registerBank('PAYSERA', $connector);

        $health = $this->monitor->checkHealth('PAYSERA');

        expect($health['status'])->toBe('unhealthy');
        expect($health['available'])->toBeFalse();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_error_status_on_exception(): void
    {
        $connector = $this->createMockConnector('PAYSERA');
        $connector->shouldReceive('isAvailable')->andThrow(new \Exception('Connection failed'));

        $this->monitor->registerBank('PAYSERA', $connector);

        $health = $this->monitor->checkHealth('PAYSERA');

        expect($health['status'])->toBe('error');
        expect($health['available'])->toBeFalse();
        expect($health['error'])->toBe('Connection failed');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_caches_health_check_results(): void
    {
        $connector = $this->createMockConnector('PAYSERA');
        // Should only be called once due to caching
        $connector->shouldReceive('isAvailable')->once()->andReturn(true);
        $connector->shouldReceive('getCapabilities')->andReturn(BankCapabilities::fromArray([]));
        $connector->shouldReceive('getSupportedCurrencies')->andReturn(['EUR']);

        $this->monitor->registerBank('PAYSERA', $connector);

        // First call
        $health1 = $this->monitor->checkHealth('PAYSERA');

        // Second call - should use cache
        $health2 = $this->monitor->checkHealth('PAYSERA');

        expect($health1)->toEqual($health2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_measures_response_time_in_milliseconds(): void
    {
        $connector = $this->createMockConnector('PAYSERA');
        $connector->shouldReceive('isAvailable')->andReturnUsing(function () {
            usleep(10000); // 10ms delay

            return true;
        });
        $connector->shouldReceive('getCapabilities')->andReturn(BankCapabilities::fromArray([]));
        $connector->shouldReceive('getSupportedCurrencies')->andReturn(['EUR']);

        $this->monitor->registerBank('PAYSERA', $connector);

        $health = $this->monitor->checkHealth('PAYSERA');

        expect($health['response_time_ms'])->toBeGreaterThan(5);
    }

    // ===========================================
    // checkAllBanks Tests
    // ===========================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_checks_all_registered_banks(): void
    {
        $connector1 = $this->createMockConnector('PAYSERA');
        $connector1->shouldReceive('isAvailable')->andReturn(true);
        $connector1->shouldReceive('getCapabilities')->andReturn(BankCapabilities::fromArray([]));
        $connector1->shouldReceive('getSupportedCurrencies')->andReturn(['EUR']);

        $connector2 = $this->createMockConnector('REVOLUT');
        $connector2->shouldReceive('isAvailable')->andReturn(false);
        $connector2->shouldReceive('getCapabilities')->andReturn(BankCapabilities::fromArray([]));

        $this->monitor->registerBank('PAYSERA', $connector1);
        $this->monitor->registerBank('REVOLUT', $connector2);

        $results = $this->monitor->checkAllBanks();

        expect($results)->toHaveKeys(['PAYSERA', 'REVOLUT']);
        expect($results['PAYSERA']['status'])->toBe('healthy');
        expect($results['REVOLUT']['status'])->toBe('unhealthy');
    }

    // ===========================================
    // getHealthMetrics Tests
    // ===========================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_calculates_health_metrics(): void
    {
        $connector1 = $this->createMockConnector('PAYSERA');
        $connector1->shouldReceive('isAvailable')->andReturn(true);
        $connector1->shouldReceive('getCapabilities')->andReturn(BankCapabilities::fromArray([]));
        $connector1->shouldReceive('getSupportedCurrencies')->andReturn(['EUR']);

        $connector2 = $this->createMockConnector('REVOLUT');
        $connector2->shouldReceive('isAvailable')->andReturn(false);
        $connector2->shouldReceive('getCapabilities')->andReturn(BankCapabilities::fromArray([]));

        $this->monitor->registerBank('PAYSERA', $connector1);
        $this->monitor->registerBank('REVOLUT', $connector2);

        $metrics = $this->monitor->getHealthMetrics();

        expect($metrics['total_banks'])->toBe(2);
        expect($metrics['healthy_banks'])->toBe(1);
        expect($metrics['unhealthy_banks'])->toBe(1);
        expect($metrics)->toHaveKey('average_response_time');
    }

    // ===========================================
    // getBanksByStatus Tests
    // ===========================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_banks_by_status(): void
    {
        $connector1 = $this->createMockConnector('PAYSERA');
        $connector1->shouldReceive('isAvailable')->andReturn(true);
        $connector1->shouldReceive('getCapabilities')->andReturn(BankCapabilities::fromArray([]));
        $connector1->shouldReceive('getSupportedCurrencies')->andReturn(['EUR']);

        $connector2 = $this->createMockConnector('REVOLUT');
        $connector2->shouldReceive('isAvailable')->andReturn(false);
        $connector2->shouldReceive('getCapabilities')->andReturn(BankCapabilities::fromArray([]));

        $this->monitor->registerBank('PAYSERA', $connector1);
        $this->monitor->registerBank('REVOLUT', $connector2);

        $healthyBanks = $this->monitor->getBanksByStatus('healthy');

        expect($healthyBanks)->toHaveCount(1);
        expect($healthyBanks)->toHaveKey('PAYSERA');
    }

    // ===========================================
    // getUptimePercentage Tests
    // ===========================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_zero_uptime_for_no_history(): void
    {
        $uptime = $this->monitor->getUptimePercentage('UNKNOWN_BANK', 24);

        expect($uptime)->toBe(0.0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_calculates_uptime_from_history(): void
    {
        // Manually set up health history in cache
        $history = [
            ['status' => 'healthy', 'timestamp' => now()->subHours(1)->toIso8601String()],
            ['status' => 'healthy', 'timestamp' => now()->subHours(2)->toIso8601String()],
            ['status' => 'unhealthy', 'timestamp' => now()->subHours(3)->toIso8601String()],
            ['status' => 'healthy', 'timestamp' => now()->subHours(4)->toIso8601String()],
        ];

        Cache::put('bank_health_history:PAYSERA', $history, now()->addDay());

        $uptime = $this->monitor->getUptimePercentage('PAYSERA', 24);

        // 3 healthy out of 4 = 75%
        expect($uptime)->toBe(75.0);
    }

    // ===========================================
    // Event Emission Tests
    // ===========================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_emits_event_when_status_changes(): void
    {
        Event::fake([BankHealthChanged::class]);

        $connector = $this->createMockConnector('PAYSERA');
        $connector->shouldReceive('isAvailable')->andReturn(true);
        $connector->shouldReceive('getCapabilities')->andReturn(BankCapabilities::fromArray([]));
        $connector->shouldReceive('getSupportedCurrencies')->andReturn(['EUR']);

        $this->monitor->registerBank('PAYSERA', $connector);

        // Set previous status as unhealthy
        Cache::put('bank_previous_status:PAYSERA', 'unhealthy', now()->addDay());

        // Clear the health cache so we get a fresh check
        Cache::forget('bank_health:PAYSERA');

        $this->monitor->checkHealth('PAYSERA');

        Event::assertDispatched(BankHealthChanged::class, function ($event) {
            return $event->bankCode === 'PAYSERA'
                && $event->previousStatus === 'unhealthy'
                && $event->currentStatus === 'healthy';
        });
    }

    // ===========================================
    // getHealthHistory Tests
    // ===========================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_health_history_by_hours(): void
    {
        $history = [
            ['status' => 'healthy', 'timestamp' => now()->subHours(1)->toIso8601String()],
            ['status' => 'healthy', 'timestamp' => now()->subHours(12)->toIso8601String()],
            ['status' => 'unhealthy', 'timestamp' => now()->subHours(48)->toIso8601String()],
        ];

        Cache::put('bank_health_history:PAYSERA', $history, now()->addDay());

        // Get last 24 hours - should exclude 48 hour old entry
        $filtered = $this->monitor->getHealthHistory('PAYSERA', 24);

        expect(count($filtered))->toBe(2);
    }

    // ===========================================
    // Helper Methods
    // ===========================================

    private function createMockConnector(string $bankCode): MockInterface
    {
        $connector = Mockery::mock(IBankConnector::class);
        $connector->shouldReceive('getBankCode')->andReturn($bankCode);
        $connector->shouldReceive('getBankName')->andReturn("{$bankCode} Bank");

        return $connector;
    }
}
