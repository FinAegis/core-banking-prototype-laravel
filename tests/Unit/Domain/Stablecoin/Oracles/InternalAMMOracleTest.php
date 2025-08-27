<?php

namespace Tests\Unit\Domain\Stablecoin\Oracles;

use App\Domain\Exchange\Projections\LiquidityPool;
use App\Domain\Stablecoin\Contracts\OracleConnector;
use App\Domain\Stablecoin\Oracles\InternalAMMOracle;
use App\Domain\Stablecoin\ValueObjects\PriceData;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class InternalAMMOracleTest extends TestCase
{
    use RefreshDatabase;

    private InternalAMMOracle $oracle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->oracle = new InternalAMMOracle();
    }

    #[Test]
    public function test_implements_oracle_connector_interface(): void
    {
        $this->assertInstanceOf(OracleConnector::class, $this->oracle);
    }

    #[Test]
    public function test_get_price_returns_valid_price_data_from_pool(): void
    {
        // Create a liquidity pool
        $pool = LiquidityPool::create([
            'pool_id'        => 'pool-123',
            'base_currency'  => 'ETH',
            'quote_currency' => 'USDT',
            'base_reserve'   => '1000.00000000', // 1000 ETH
            'quote_reserve'  => '3200000.00000000', // 3.2M USDT
            'total_shares'   => '1000000.00000000',
            'is_active'      => true,
            'volume_24h'     => '500000.00000000',
            'updated_at'     => Carbon::now(),
        ]);

        $priceData = $this->oracle->getPrice('ETH', 'USDT');

        $this->assertInstanceOf(PriceData::class, $priceData);
        $this->assertEquals('ETH', $priceData->base);
        $this->assertEquals('USDT', $priceData->quote);
        $this->assertEquals('internal_amm', $priceData->source);
        $this->assertEquals('3200.00000000', $priceData->price); // 3,200,000 / 1,000 = 3,200
        $this->assertEquals($pool->updated_at->toIso8601String(), $priceData->timestamp->toIso8601String());
        // Volume from database might not have trailing zeros
        $this->assertEquals(500000, (float) $priceData->volume24h);
        $this->assertNull($priceData->changePercent24h);

        // Check metadata
        $this->assertEquals('pool-123', $priceData->metadata['pool_id']);
        $this->assertEquals(3201000, (float) $priceData->metadata['liquidity']);
        $this->assertEquals(1000000, (float) $priceData->metadata['total_shares']);
        $this->assertEquals(3200000000, (float) $priceData->metadata['k_value']); // 1000 * 3,200,000
    }

    #[Test]
    public function test_get_price_with_inverted_pair(): void
    {
        // Create pool with USDT/ETH (inverted from what we request)
        LiquidityPool::create([
            'pool_id'        => 'pool-456',
            'base_currency'  => 'USDT',
            'quote_currency' => 'ETH',
            'base_reserve'   => '3200000.00000000', // 3.2M USDT
            'quote_reserve'  => '1000.00000000', // 1000 ETH
            'total_shares'   => '1000000.00000000',
            'is_active'      => true,
            'volume_24h'     => '500000.00000000',
        ]);

        $priceData = $this->oracle->getPrice('ETH', 'USDT');

        $this->assertEquals('ETH', $priceData->base);
        $this->assertEquals('USDT', $priceData->quote);
        $this->assertEquals('3200.00000000', $priceData->price); // Correctly inverted
    }

    #[Test]
    public function test_get_price_throws_exception_when_no_pool_found(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No liquidity pool found for BTC/USDT');

        $this->oracle->getPrice('BTC', 'USDT');
    }

    #[Test]
    public function test_get_multiple_prices_returns_array_of_price_data(): void
    {
        // Create multiple pools
        LiquidityPool::create([
            'pool_id'        => 'pool-btc',
            'base_currency'  => 'BTC',
            'quote_currency' => 'USDT',
            'base_reserve'   => '100.00000000',
            'quote_reserve'  => '4800000.00000000',
            'total_shares'   => '100000.00000000',
            'is_active'      => true,
        ]);

        LiquidityPool::create([
            'pool_id'        => 'pool-eth',
            'base_currency'  => 'ETH',
            'quote_currency' => 'USDT',
            'base_reserve'   => '1000.00000000',
            'quote_reserve'  => '3200000.00000000',
            'total_shares'   => '100000.00000000',
            'is_active'      => true,
        ]);

        $pairs = ['BTC/USDT', 'ETH/USDT'];
        $prices = $this->oracle->getMultiplePrices($pairs);

        $this->assertIsArray($prices);
        $this->assertCount(2, $prices);
        $this->assertArrayHasKey('BTC/USDT', $prices);
        $this->assertArrayHasKey('ETH/USDT', $prices);
        $this->assertEquals('48000.00000000', $prices['BTC/USDT']->price);
        $this->assertEquals('3200.00000000', $prices['ETH/USDT']->price);
    }

    #[Test]
    public function test_get_multiple_prices_handles_missing_pools_gracefully(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with(Mockery::pattern('/Internal AMM oracle error:/'));

        Log::shouldReceive('warning')
            ->once()
            ->with(Mockery::pattern('/Failed to get AMM price for XRP\/USDT:/'));

        LiquidityPool::create([
            'pool_id'        => 'pool-btc',
            'base_currency'  => 'BTC',
            'quote_currency' => 'USDT',
            'base_reserve'   => '100.00000000',
            'quote_reserve'  => '4800000.00000000',
            'total_shares'   => '100000.00000000',
            'is_active'      => true,
        ]);

        $pairs = ['BTC/USDT', 'XRP/USDT'];
        $prices = $this->oracle->getMultiplePrices($pairs);

        $this->assertCount(1, $prices);
        $this->assertArrayHasKey('BTC/USDT', $prices);
        $this->assertArrayNotHasKey('XRP/USDT', $prices);
    }

    #[Test]
    public function test_get_historical_price_throws_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Historical AMM prices not yet implemented');

        $this->oracle->getHistoricalPrice('ETH', 'USDT', Carbon::now()->subDay());
    }

    #[Test]
    public function test_is_healthy_returns_true_when_active_pools_exist(): void
    {
        LiquidityPool::create([
            'pool_id'        => 'pool-active',
            'base_currency'  => 'ETH',
            'quote_currency' => 'USDT',
            'base_reserve'   => '1000.00000000',
            'quote_reserve'  => '3200000.00000000',
            'total_shares'   => '100000.00000000',
            'is_active'      => true,
        ]);

        $this->assertTrue($this->oracle->isHealthy());
    }

    #[Test]
    public function test_is_healthy_returns_false_when_no_active_pools(): void
    {
        // Create only inactive pool
        LiquidityPool::create([
            'pool_id'        => 'pool-inactive',
            'base_currency'  => 'ETH',
            'quote_currency' => 'USDT',
            'base_reserve'   => '1000.00000000',
            'quote_reserve'  => '3200000.00000000',
            'total_shares'   => '100000.00000000',
            'is_active'      => false,
        ]);

        $this->assertFalse($this->oracle->isHealthy());
    }

    #[Test]
    public function test_is_healthy_returns_false_when_no_pools_exist(): void
    {
        $this->assertFalse($this->oracle->isHealthy());
    }

    #[Test]
    public function test_get_source_name_returns_internal_amm(): void
    {
        $this->assertEquals('internal_amm', $this->oracle->getSourceName());
    }

    #[Test]
    public function test_get_priority_returns_3(): void
    {
        $this->assertEquals(3, $this->oracle->getPriority());
    }

    #[Test]
    public function test_calculate_price_with_different_pool_reserves(): void
    {
        $testCases = [
            // [base_reserve, quote_reserve, expected_price]
            ['1.00000000', '1000.00000000', '1000.00000000'],
            ['10.00000000', '10000.00000000', '1000.00000000'],
            ['500.00000000', '1000000.00000000', '2000.00000000'],
            ['2000.00000000', '4000000.00000000', '2000.00000000'],
        ];

        foreach ($testCases as $i => $case) {
            LiquidityPool::create([
                'pool_id'        => "pool-test-{$i}",
                'base_currency'  => 'TOKEN',
                'quote_currency' => 'USDT',
                'base_reserve'   => $case[0],
                'quote_reserve'  => $case[1],
                'total_shares'   => '100000.00000000',
                'is_active'      => true,
            ]);

            $priceData = $this->oracle->getPrice('TOKEN', 'USDT');
            $this->assertEquals($case[2], $priceData->price);

            // Clean up for next iteration
            LiquidityPool::where('pool_id', "pool-test-{$i}")->delete();
        }
    }

    #[Test]
    public function test_price_calculation_maintains_precision(): void
    {
        LiquidityPool::create([
            'pool_id'        => 'pool-precision',
            'base_currency'  => 'BTC',
            'quote_currency' => 'USDT',
            'base_reserve'   => '1.23456789',
            'quote_reserve'  => '59259.25925926',
            'total_shares'   => '100.00000000',
            'is_active'      => true,
        ]);

        $priceData = $this->oracle->getPrice('BTC', 'USDT');

        // Should maintain 8 decimal places
        $this->assertMatchesRegularExpression('/^\d+\.\d{8}$/', $priceData->price);
        // Price should be approximately 48000
        $this->assertGreaterThan(47999, (float) $priceData->price);
        $this->assertLessThan(48001, (float) $priceData->price);
    }

    #[Test]
    public function test_k_value_calculation_is_correct(): void
    {
        LiquidityPool::create([
            'pool_id'        => 'pool-k-test',
            'base_currency'  => 'ETH',
            'quote_currency' => 'DAI',
            'base_reserve'   => '1000.12345678',
            'quote_reserve'  => '3200000.87654321',
            'total_shares'   => '100000.00000000',
            'is_active'      => true,
        ]);

        $priceData = $this->oracle->getPrice('ETH', 'DAI');

        $expectedK = BigDecimal::of('1000.12345678')
            ->multipliedBy('3200000.87654321');

        // Compare as floats with tolerance due to precision differences
        $this->assertEqualsWithDelta(
            (float) $expectedK->__toString(),
            (float) $priceData->metadata['k_value'],
            0.01
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
