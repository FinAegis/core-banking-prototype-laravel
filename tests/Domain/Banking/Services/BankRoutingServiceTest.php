<?php

declare(strict_types=1);

namespace Tests\Domain\Banking\Services;

use App\Domain\Banking\Services\BankHealthMonitor;
use App\Domain\Banking\Services\BankRoutingService;
use App\Models\User;
use Mockery\LegacyMockInterface;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Tests\UnitTestCase;

class BankRoutingServiceTest extends UnitTestCase
{
    private BankRoutingService $service;

    private MockInterface $healthMonitor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->healthMonitor = Mockery::mock(BankHealthMonitor::class);
        $this->service = new BankRoutingService($this->healthMonitor);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ===========================================
    // determineTransferType Tests
    // ===========================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_internal_for_same_bank_transfer(): void
    {
        $result = $this->service->determineTransferType('PAYSERA', 'PAYSERA', 'EUR', 1000);

        expect($result)->toBe('INTERNAL');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_sepa_instant_for_eur_under_15000_between_european_banks(): void
    {
        $result = $this->service->determineTransferType('PAYSERA', 'REVOLUT', 'EUR', 14999);

        expect($result)->toBe('SEPA_INSTANT');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_sepa_instant_for_eur_at_exactly_15000(): void
    {
        $result = $this->service->determineTransferType('WISE', 'DEUTSCHE', 'EUR', 15000);

        expect($result)->toBe('SEPA_INSTANT');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_sepa_for_eur_over_15000_between_european_banks(): void
    {
        $result = $this->service->determineTransferType('PAYSERA', 'SANTANDER', 'EUR', 15001);

        expect($result)->toBe('SEPA');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_swift_for_non_eur_currency_between_european_banks(): void
    {
        $result = $this->service->determineTransferType('PAYSERA', 'REVOLUT', 'USD', 1000);

        expect($result)->toBe('SWIFT');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_swift_for_non_european_banks(): void
    {
        $result = $this->service->determineTransferType('PAYSERA', 'CHASE', 'EUR', 1000);

        expect($result)->toBe('SWIFT');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_swift_for_international_transfer(): void
    {
        $result = $this->service->determineTransferType('UNKNOWN_BANK', 'ANOTHER_BANK', 'GBP', 5000);

        expect($result)->toBe('SWIFT');
    }

    // ===========================================
    // getOptimalBank Tests
    // ===========================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_bank_with_highest_score(): void
    {
        $user = Mockery::mock(User::class);

        // Create mock connections
        $connection1 = $this->createMockConnection('PAYSERA', true);
        $connection2 = $this->createMockConnection('REVOLUT', true);

        $userConnections = new Collection([$connection1, $connection2]);

        // Mock health checks - REVOLUT has better health
        $this->healthMonitor->shouldReceive('checkHealth')
            ->with('PAYSERA')
            ->andReturn([
                'status'           => 'healthy',
                'response_time_ms' => 500,
                'capabilities'     => [
                    'supported_currencies'       => ['EUR', 'USD'],
                    'supported_transfer_types'   => ['SEPA', 'SWIFT'],
                    'supports_instant_transfers' => false,
                ],
            ]);

        $this->healthMonitor->shouldReceive('checkHealth')
            ->with('REVOLUT')
            ->andReturn([
                'status'           => 'healthy',
                'response_time_ms' => 100,
                'capabilities'     => [
                    'supported_currencies'       => ['EUR', 'USD', 'GBP'],
                    'supported_transfer_types'   => ['SEPA', 'SEPA_INSTANT', 'SWIFT'],
                    'supports_instant_transfers' => true,
                ],
            ]);

        $this->healthMonitor->shouldReceive('getUptimePercentage')
            ->with('PAYSERA', 24)
            ->andReturn(95.0);

        $this->healthMonitor->shouldReceive('getUptimePercentage')
            ->with('REVOLUT', 24)
            ->andReturn(99.5);

        $result = $this->service->getOptimalBank($user, 'EUR', 1000, 'SEPA', $userConnections);

        // REVOLUT should win due to faster response time, better uptime, and lower fees
        expect($result)->toBe('REVOLUT');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_skips_inactive_connections(): void
    {
        $user = Mockery::mock(User::class);

        // Create mock connections - one inactive
        $connection1 = $this->createMockConnection('PAYSERA', false);  // inactive
        $connection2 = $this->createMockConnection('WISE', true);

        $userConnections = new Collection([$connection1, $connection2]);

        // Only WISE should be checked
        $this->healthMonitor->shouldReceive('checkHealth')
            ->with('WISE')
            ->once()
            ->andReturn([
                'status'           => 'healthy',
                'response_time_ms' => 200,
                'capabilities'     => [
                    'supported_currencies'     => ['EUR'],
                    'supported_transfer_types' => ['SEPA'],
                ],
            ]);

        $this->healthMonitor->shouldReceive('getUptimePercentage')
            ->with('WISE', 24)
            ->andReturn(98.0);

        $result = $this->service->getOptimalBank($user, 'EUR', 1000, 'SEPA', $userConnections);

        expect($result)->toBe('WISE');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_prefers_healthy_bank_over_unhealthy(): void
    {
        $user = Mockery::mock(User::class);

        $connection1 = $this->createMockConnection('PAYSERA', true);
        $connection2 = $this->createMockConnection('REVOLUT', true);

        $userConnections = new Collection([$connection1, $connection2]);

        // PAYSERA is unhealthy
        $this->healthMonitor->shouldReceive('checkHealth')
            ->with('PAYSERA')
            ->andReturn(['status' => 'unhealthy']);

        $this->healthMonitor->shouldReceive('checkHealth')
            ->with('REVOLUT')
            ->andReturn([
                'status'           => 'healthy',
                'response_time_ms' => 300,
                'capabilities'     => [],
            ]);

        $this->healthMonitor->shouldReceive('getUptimePercentage')
            ->andReturn(90.0);

        $result = $this->service->getOptimalBank($user, 'EUR', 1000, 'SEPA', $userConnections);

        expect($result)->toBe('REVOLUT');
    }

    // ===========================================
    // getRecommendedBanks Tests
    // ===========================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_recommended_banks_by_currency(): void
    {
        $user = Mockery::mock(User::class);

        $this->healthMonitor->shouldReceive('checkAllBanks')
            ->once()
            ->andReturn([
                'PAYSERA' => [
                    'status'               => 'healthy',
                    'supported_currencies' => ['EUR', 'USD'],
                    'capabilities'         => [],
                ],
                'REVOLUT' => [
                    'status'               => 'healthy',
                    'supported_currencies' => ['EUR', 'USD', 'GBP'],
                    'capabilities'         => [],
                ],
                'WISE' => [
                    'status'               => 'healthy',
                    'supported_currencies' => ['USD'],  // Missing EUR
                    'capabilities'         => [],
                ],
            ]);

        $result = $this->service->getRecommendedBanks($user, [
            'currencies' => ['EUR', 'USD'],
        ]);

        expect($result)->toHaveCount(2);
        expect(array_column($result, 'bank_code'))->toContain('PAYSERA', 'REVOLUT');
        expect(array_column($result, 'bank_code'))->not->toContain('WISE');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_recommended_banks_by_features(): void
    {
        $user = Mockery::mock(User::class);

        $this->healthMonitor->shouldReceive('checkAllBanks')
            ->once()
            ->andReturn([
                'PAYSERA' => [
                    'status'       => 'healthy',
                    'capabilities' => [
                        'supports_instant_transfers' => true,
                        'supports_webhooks'          => true,
                    ],
                ],
                'REVOLUT' => [
                    'status'       => 'healthy',
                    'capabilities' => [
                        'supports_instant_transfers' => false,
                        'supports_webhooks'          => true,
                    ],
                ],
            ]);

        $result = $this->service->getRecommendedBanks($user, [
            'features' => ['instant_transfers'],
        ]);

        // Only PAYSERA should be recommended with high score
        expect($result[0]['bank_code'])->toBe('PAYSERA');
        expect($result[0]['score'])->toBeGreaterThan($result[1]['score'] ?? 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_excludes_unhealthy_banks_from_recommendations(): void
    {
        $user = Mockery::mock(User::class);

        $this->healthMonitor->shouldReceive('checkAllBanks')
            ->once()
            ->andReturn([
                'PAYSERA' => [
                    'status'               => 'unhealthy',
                    'supported_currencies' => ['EUR'],
                ],
                'REVOLUT' => [
                    'status'               => 'healthy',
                    'supported_currencies' => ['EUR'],
                    'capabilities'         => [],
                ],
            ]);

        $result = $this->service->getRecommendedBanks($user, [
            'currencies' => ['EUR'],
        ]);

        expect($result)->toHaveCount(1);
        expect($result[0]['bank_code'])->toBe('REVOLUT');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sorts_recommendations_by_score(): void
    {
        $user = Mockery::mock(User::class);

        $this->healthMonitor->shouldReceive('checkAllBanks')
            ->once()
            ->andReturn([
                'PAYSERA' => [
                    'status'               => 'healthy',
                    'supported_currencies' => ['EUR'],
                    'capabilities'         => [],
                ],
                'REVOLUT' => [
                    'status'               => 'healthy',
                    'supported_currencies' => ['EUR', 'USD'],
                    'capabilities'         => [
                        'supports_instant_transfers' => true,
                    ],
                ],
                'WISE' => [
                    'status'               => 'healthy',
                    'supported_currencies' => ['EUR'],
                    'capabilities'         => [
                        'supports_instant_transfers' => true,
                        'supports_multi_currency'    => true,
                    ],
                ],
            ]);

        $result = $this->service->getRecommendedBanks($user, [
            'currencies' => ['EUR'],
            'features'   => ['instant_transfers', 'multi_currency'],
        ]);

        // Results should be sorted by score descending
        expect($result[0]['score'])->toBeGreaterThanOrEqual($result[1]['score']);
        if (isset($result[2])) {
            expect($result[1]['score'])->toBeGreaterThanOrEqual($result[2]['score']);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_recommended_banks_by_countries(): void
    {
        $user = Mockery::mock(User::class);

        $this->healthMonitor->shouldReceive('checkAllBanks')
            ->once()
            ->andReturn([
                'PAYSERA' => [
                    'status'       => 'healthy',
                    'capabilities' => [
                        'available_countries' => ['LT', 'PL', 'DE'],
                    ],
                ],
                'REVOLUT' => [
                    'status'       => 'healthy',
                    'capabilities' => [
                        'available_countries' => ['GB', 'IE'],
                    ],
                ],
            ]);

        $result = $this->service->getRecommendedBanks($user, [
            'countries' => ['DE'],
        ]);

        expect($result)->toHaveCount(1);
        expect($result[0]['bank_code'])->toBe('PAYSERA');
    }

    // ===========================================
    // Helper Methods
    // ===========================================

    private function createMockConnection(string $bankCode, bool $isActive): object
    {
        return new class($bankCode, $isActive) {
            public function __construct(
                public string $bankCode,
                private bool $active
            ) {
            }

            public function isActive(): bool
            {
                return $this->active;
            }
        };
    }
}
