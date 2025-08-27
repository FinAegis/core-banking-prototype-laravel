<?php

namespace Tests\Unit\Domain\Stablecoin\Oracles;

use App\Domain\Stablecoin\Contracts\OracleConnector;
use App\Domain\Stablecoin\Oracles\ChainlinkOracle;
use App\Domain\Stablecoin\ValueObjects\PriceData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ChainlinkOracleTest extends TestCase
{
    private ChainlinkOracle $oracle;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('stablecoin.oracles.chainlink.api_key', 'test-api-key');
        Config::set('stablecoin.oracles.chainlink.base_url', 'https://api.chain.link/v1');

        $this->oracle = new ChainlinkOracle();
    }

    #[Test]
    public function test_implements_oracle_connector_interface(): void
    {
        $this->assertInstanceOf(OracleConnector::class, $this->oracle);
    }

    #[Test]
    public function test_get_price_returns_valid_price_data(): void
    {
        $priceData = $this->oracle->getPrice('BTC', 'USD');

        $this->assertInstanceOf(PriceData::class, $priceData);
        $this->assertEquals('BTC', $priceData->base);
        $this->assertEquals('USD', $priceData->quote);
        $this->assertEquals('chainlink', $priceData->source);
        $this->assertIsString($priceData->price);
        $this->assertGreaterThan(0, (float) $priceData->price);
        $this->assertInstanceOf(Carbon::class, $priceData->timestamp);
        $this->assertNull($priceData->volume24h);
        $this->assertNull($priceData->changePercent24h);
        $this->assertArrayHasKey('feed_address', $priceData->metadata);
        $this->assertArrayHasKey('round_id', $priceData->metadata);
        $this->assertArrayHasKey('updated_at', $priceData->metadata);
    }

    #[Test]
    public function test_get_price_for_supported_pairs(): void
    {
        $pairs = [
            ['base' => 'BTC', 'quote' => 'USD', 'feed' => '0xF4030086522a5bEEa4988F8cA5B36dbC97BeE88c'],
            ['base' => 'ETH', 'quote' => 'USD', 'feed' => '0x5f4eC3Df9cbd43714FE2740f5E3616155c5b8419'],
            ['base' => 'EUR', 'quote' => 'USD', 'feed' => '0xb49f677943BC038e9857d61E7d053CaA2C1734C1'],
            ['base' => 'GBP', 'quote' => 'USD', 'feed' => '0x5c0Ab2d9b5a7ed9f470386e82BB36A3613cDd4b5'],
        ];

        foreach ($pairs as $pair) {
            $priceData = $this->oracle->getPrice($pair['base'], $pair['quote']);

            $this->assertEquals($pair['base'], $priceData->base);
            $this->assertEquals($pair['quote'], $priceData->quote);
            $this->assertEquals($pair['feed'], $priceData->metadata['feed_address']);
        }
    }

    #[Test]
    public function test_get_price_throws_exception_for_unsupported_pair(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Price feed not available for XRP/USD');

        $this->oracle->getPrice('XRP', 'USD');
    }

    #[Test]
    public function test_get_multiple_prices_returns_array_of_price_data(): void
    {
        $pairs = ['BTC/USD', 'ETH/USD'];

        $prices = $this->oracle->getMultiplePrices($pairs);

        $this->assertIsArray($prices);
        $this->assertCount(2, $prices);
        $this->assertArrayHasKey('BTC/USD', $prices);
        $this->assertArrayHasKey('ETH/USD', $prices);
        $this->assertInstanceOf(PriceData::class, $prices['BTC/USD']);
        $this->assertInstanceOf(PriceData::class, $prices['ETH/USD']);
    }

    #[Test]
    public function test_get_multiple_prices_skips_unsupported_pairs(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with(Mockery::pattern('/Failed to get price for XRP\/USD/'));

        $pairs = ['BTC/USD', 'XRP/USD', 'ETH/USD'];

        $prices = $this->oracle->getMultiplePrices($pairs);

        $this->assertCount(2, $prices);
        $this->assertArrayHasKey('BTC/USD', $prices);
        $this->assertArrayHasKey('ETH/USD', $prices);
        $this->assertArrayNotHasKey('XRP/USD', $prices);
    }

    #[Test]
    public function test_get_historical_price_throws_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Historical prices not available from Chainlink oracle');

        $this->oracle->getHistoricalPrice('BTC', 'USD', Carbon::now()->subDay());
    }

    #[Test]
    public function test_is_healthy_returns_true_when_can_fetch_price(): void
    {
        $isHealthy = $this->oracle->isHealthy();

        $this->assertTrue($isHealthy);
    }

    #[Test]
    public function test_get_source_name_returns_chainlink(): void
    {
        $this->assertEquals('chainlink', $this->oracle->getSourceName());
    }

    #[Test]
    public function test_get_priority_returns_1(): void
    {
        $this->assertEquals(1, $this->oracle->getPriority());
    }

    #[Test]
    public function test_simulated_prices_are_within_expected_ranges(): void
    {
        // Run multiple times to test randomness
        for ($i = 0; $i < 10; $i++) {
            $btcPrice = $this->oracle->getPrice('BTC', 'USD');
            $btcPriceFloat = (float) $btcPrice->price;
            $this->assertGreaterThanOrEqual(47000, $btcPriceFloat);
            $this->assertLessThanOrEqual(49000, $btcPriceFloat);

            $ethPrice = $this->oracle->getPrice('ETH', 'USD');
            $ethPriceFloat = (float) $ethPrice->price;
            $this->assertGreaterThanOrEqual(3100, $ethPriceFloat);
            $this->assertLessThanOrEqual(3300, $ethPriceFloat);
        }
    }

    #[Test]
    public function test_non_usd_quote_conversion(): void
    {
        // Test EUR/USD which is a supported pair
        $priceData = $this->oracle->getPrice('EUR', 'USD');

        $this->assertEquals('EUR', $priceData->base);
        $this->assertEquals('USD', $priceData->quote);

        // EUR/USD should be around 1.04 to 1.14 based on simulation
        $eurUsdPrice = (float) $priceData->price;
        $this->assertGreaterThanOrEqual(1.04, $eurUsdPrice);
        $this->assertLessThanOrEqual(1.14, $eurUsdPrice);
    }

    #[Test]
    public function test_price_data_has_recent_timestamp(): void
    {
        $priceData = $this->oracle->getPrice('ETH', 'USD');

        $now = Carbon::now();
        $diff = $now->diffInSeconds($priceData->timestamp);

        $this->assertLessThanOrEqual(5, $diff); // Within 5 seconds
    }

    #[Test]
    public function test_metadata_contains_valid_round_id(): void
    {
        $priceData = $this->oracle->getPrice('BTC', 'USD');

        $this->assertArrayHasKey('round_id', $priceData->metadata);
        $this->assertIsInt($priceData->metadata['round_id']);
        $this->assertGreaterThanOrEqual(1000000, $priceData->metadata['round_id']);
        $this->assertLessThanOrEqual(9999999, $priceData->metadata['round_id']);
    }

    #[Test]
    public function test_metadata_contains_valid_updated_at(): void
    {
        $priceData = $this->oracle->getPrice('ETH', 'USD');

        $this->assertArrayHasKey('updated_at', $priceData->metadata);
        $this->assertIsInt($priceData->metadata['updated_at']);

        // Updated at should be within last 5 minutes
        $now = time();
        $updatedAt = $priceData->metadata['updated_at'];
        $this->assertGreaterThanOrEqual($now - 300, $updatedAt);
        $this->assertLessThanOrEqual($now, $updatedAt);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
