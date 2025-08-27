<?php

namespace Tests\Unit\Domain\Stablecoin\Oracles;

use App\Domain\Stablecoin\Contracts\OracleConnector;
use App\Domain\Stablecoin\Oracles\BinanceOracle;
use App\Domain\Stablecoin\ValueObjects\PriceData;
use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BinanceOracleTest extends TestCase
{
    use WithoutMiddleware;

    private BinanceOracle $oracle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->oracle = new BinanceOracle();
    }

    #[Test]
    public function test_implements_oracle_connector_interface(): void
    {
        $this->assertInstanceOf(OracleConnector::class, $this->oracle);
    }

    #[Test]
    public function test_get_price_returns_valid_price_data(): void
    {
        $priceData = $this->oracle->getPrice('BTC', 'USDT');

        $this->assertInstanceOf(PriceData::class, $priceData);
        $this->assertEquals('BTC', $priceData->base);
        $this->assertEquals('USDT', $priceData->quote);
        $this->assertEquals('binance', $priceData->source);
        $this->assertIsString($priceData->price);
        $this->assertGreaterThan(0, (float) $priceData->price);
        $this->assertInstanceOf(Carbon::class, $priceData->timestamp);
        $this->assertNotNull($priceData->volume24h);
        $this->assertNotNull($priceData->changePercent24h);
        $this->assertArrayHasKey('symbol', $priceData->metadata);
        $this->assertArrayHasKey('quote_volume', $priceData->metadata);
        $this->assertArrayHasKey('count', $priceData->metadata);
    }

    #[Test]
    public function test_get_price_with_mapped_symbols(): void
    {
        $mappedPairs = [
            ['base' => 'BTC', 'quote' => 'USDT', 'symbol' => 'BTCUSDT'],
            ['base' => 'ETH', 'quote' => 'USDT', 'symbol' => 'ETHUSDT'],
            ['base' => 'BTC', 'quote' => 'USD', 'symbol' => 'BTCUSDC'],
            ['base' => 'ETH', 'quote' => 'USD', 'symbol' => 'ETHUSDC'],
            ['base' => 'EUR', 'quote' => 'USD', 'symbol' => 'EURUSDT'],
            ['base' => 'GBP', 'quote' => 'USD', 'symbol' => 'GBPUSDT'],
        ];

        foreach ($mappedPairs as $pair) {
            $priceData = $this->oracle->getPrice($pair['base'], $pair['quote']);

            $this->assertEquals($pair['base'], $priceData->base);
            $this->assertEquals($pair['quote'], $priceData->quote);
            $this->assertEquals($pair['symbol'], $priceData->metadata['symbol']);
        }
    }

    #[Test]
    public function test_get_price_constructs_symbol_for_unmapped_pairs(): void
    {
        $priceData = $this->oracle->getPrice('ADA', 'USDT');

        $this->assertEquals('ADA', $priceData->base);
        $this->assertEquals('USDT', $priceData->quote);
        $this->assertEquals('ADAUSDT', $priceData->metadata['symbol']);
    }

    #[Test]
    public function test_get_price_converts_usd_to_usdt(): void
    {
        $priceData = $this->oracle->getPrice('SOL', 'USD');

        $this->assertEquals('SOL', $priceData->base);
        $this->assertEquals('USD', $priceData->quote);
        $this->assertEquals('SOLUSDT', $priceData->metadata['symbol']);
    }

    #[Test]
    public function test_get_multiple_prices_returns_array_of_price_data(): void
    {
        $pairs = ['BTC/USDT', 'ETH/USDT', 'EUR/USD'];

        $prices = $this->oracle->getMultiplePrices($pairs);

        $this->assertIsArray($prices);
        $this->assertCount(3, $prices);

        foreach ($pairs as $pair) {
            $this->assertArrayHasKey($pair, $prices);
            $this->assertInstanceOf(PriceData::class, $prices[$pair]);
        }
    }

    #[Test]
    public function test_get_multiple_prices_handles_errors_gracefully(): void
    {
        // Use spy instead of mock for Log to avoid expectation issues
        Log::spy();

        // Create a partial mock that throws exception for specific pair
        $oracle = Mockery::mock(BinanceOracle::class)->makePartial();
        $oracle->shouldReceive('getPrice')
            ->with('INVALID', 'PAIR')
            ->andThrow(new Exception('Invalid pair'));
        $oracle->shouldReceive('getPrice')
            ->with('BTC', 'USDT')
            ->andReturn(new PriceData('BTC', 'USDT', '48000', 'binance', Carbon::now()));

        $prices = $oracle->getMultiplePrices(['BTC/USDT', 'INVALID/PAIR']);

        $this->assertCount(1, $prices);
        $this->assertArrayHasKey('BTC/USDT', $prices);
        $this->assertArrayNotHasKey('INVALID/PAIR', $prices);

        // Only verify the warning log since getMultiplePrices catches the exception
        Log::shouldHaveReceived('warning')
            ->with(Mockery::pattern('/Failed to get price for INVALID\/PAIR:/'));
    }

    #[Test]
    public function test_get_historical_price_returns_valid_data(): void
    {
        $timestamp = Carbon::now()->subHour();
        $priceData = $this->oracle->getHistoricalPrice('BTC', 'USDT', $timestamp);

        $this->assertInstanceOf(PriceData::class, $priceData);
        $this->assertEquals('BTC', $priceData->base);
        $this->assertEquals('USDT', $priceData->quote);
        $this->assertEquals('binance', $priceData->source);
        $this->assertEquals($timestamp->toIso8601String(), $priceData->timestamp->toIso8601String());
        $this->assertNotNull($priceData->volume24h);
        $this->assertNull($priceData->changePercent24h);

        // Check OHLC data in metadata
        $this->assertArrayHasKey('open', $priceData->metadata);
        $this->assertArrayHasKey('high', $priceData->metadata);
        $this->assertArrayHasKey('low', $priceData->metadata);
        $this->assertIsString($priceData->metadata['open']);
        $this->assertIsString($priceData->metadata['high']);
        $this->assertIsString($priceData->metadata['low']);
    }

    #[Test]
    public function test_historical_price_high_is_greater_than_or_equal_to_open_and_close(): void
    {
        $timestamp = Carbon::now()->subDay();
        $priceData = $this->oracle->getHistoricalPrice('ETH', 'USDT', $timestamp);

        $open = (float) $priceData->metadata['open'];
        $high = (float) $priceData->metadata['high'];
        $low = (float) $priceData->metadata['low'];
        $close = (float) $priceData->price;

        $this->assertGreaterThanOrEqual($open, $high);
        $this->assertGreaterThanOrEqual($close, $high);
        $this->assertLessThanOrEqual($open, $low);
        $this->assertLessThanOrEqual($close, $low);
    }

    #[Test]
    public function test_is_healthy_returns_true_when_api_responds(): void
    {
        Http::fake([
            'https://api.binance.com/api/v3/ping' => Http::response([], 200),
        ]);

        $this->assertTrue($this->oracle->isHealthy());
    }

    #[Test]
    public function test_is_healthy_returns_false_when_api_fails(): void
    {
        Http::fake([
            'https://api.binance.com/api/v3/ping' => Http::response([], 503),
        ]);

        $this->assertFalse($this->oracle->isHealthy());
    }

    #[Test]
    public function test_is_healthy_returns_false_on_timeout(): void
    {
        Http::fake(function () {
            throw new Exception('Connection timeout');
        });

        $this->assertFalse($this->oracle->isHealthy());
    }

    #[Test]
    public function test_get_source_name_returns_binance(): void
    {
        $this->assertEquals('binance', $this->oracle->getSourceName());
    }

    #[Test]
    public function test_get_priority_returns_2(): void
    {
        $this->assertEquals(2, $this->oracle->getPriority());
    }

    #[Test]
    public function test_price_data_includes_volume_and_change_percent(): void
    {
        $priceData = $this->oracle->getPrice('BTC', 'USDT');

        $this->assertNotNull($priceData->volume24h);
        $this->assertNotNull($priceData->changePercent24h);
        $this->assertIsString($priceData->volume24h);
        $this->assertIsString($priceData->changePercent24h);
        $this->assertGreaterThan(0, (float) $priceData->volume24h);
        $this->assertGreaterThanOrEqual(-100, (float) $priceData->changePercent24h);
        $this->assertLessThanOrEqual(100, (float) $priceData->changePercent24h);
    }

    #[Test]
    public function test_metadata_includes_quote_volume_and_count(): void
    {
        $priceData = $this->oracle->getPrice('ETH', 'USDT');

        $this->assertArrayHasKey('quote_volume', $priceData->metadata);
        $this->assertArrayHasKey('count', $priceData->metadata);
        $this->assertIsString($priceData->metadata['quote_volume']);
        $this->assertIsInt($priceData->metadata['count']);
        $this->assertGreaterThan(0, (float) $priceData->metadata['quote_volume']);
        $this->assertGreaterThan(0, $priceData->metadata['count']);
    }

    #[Test]
    public function test_timestamp_is_recent(): void
    {
        $priceData = $this->oracle->getPrice('BTC', 'USDT');

        $now = Carbon::now();
        $diff = $now->diffInSeconds($priceData->timestamp);

        $this->assertLessThanOrEqual(5, $diff); // Within 5 seconds
    }

    #[Test]
    public function test_simulated_prices_are_within_expected_ranges(): void
    {
        // Run multiple times to test randomness
        for ($i = 0; $i < 10; $i++) {
            $btcPrice = $this->oracle->getPrice('BTC', 'USDT');
            $btcPriceFloat = (float) $btcPrice->price;
            // Base price 48000 +/- 1% (480)
            $this->assertGreaterThanOrEqual(47520, $btcPriceFloat);
            $this->assertLessThanOrEqual(48480, $btcPriceFloat);

            $ethPrice = $this->oracle->getPrice('ETH', 'USDT');
            $ethPriceFloat = (float) $ethPrice->price;
            // Base price 3200 +/- 1% (32)
            $this->assertGreaterThanOrEqual(3168, $ethPriceFloat);
            $this->assertLessThanOrEqual(3232, $ethPriceFloat);
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
