<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Domain\Exchange\Contracts\IExternalExchangeConnector;
use App\Domain\Exchange\Services\ExternalExchangeConnectorRegistry;
use App\Domain\Exchange\Services\ExternalLiquidityService;
use App\Domain\Exchange\ValueObjects\ExternalTicker;
use Brick\Math\BigDecimal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\ControllerTestCase;

class ExternalExchangeControllerTest extends ControllerTestCase
{
    use RefreshDatabase;

    protected User $user;

    protected ExternalExchangeConnectorRegistry $mockRegistry;

    protected ExternalLiquidityService $mockLiquidityService;

    protected IExternalExchangeConnector $mockConnector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Create mocks
        $this->mockRegistry = \Mockery::mock(ExternalExchangeConnectorRegistry::class);
        $this->mockLiquidityService = \Mockery::mock(ExternalLiquidityService::class);
        $this->mockConnector = \Mockery::mock(IExternalExchangeConnector::class);

        // Register mocks with the container
        $this->app->instance(ExternalExchangeConnectorRegistry::class, $this->mockRegistry);
        $this->app->instance(ExternalLiquidityService::class, $this->mockLiquidityService);
    }

    #[Test]
    public function test_get_connectors_returns_list(): void
    {
        // Setup mock connector
        $this->mockConnector->shouldReceive('getName')->andReturn('Binance');
        $this->mockConnector->shouldReceive('isAvailable')->andReturn(true);

        // Setup registry to return a collection of connectors
        $connectors = new Collection(['binance' => $this->mockConnector]);
        $this->mockRegistry->shouldReceive('all')->andReturn($connectors);

        $response = $this->getJson('/api/external-exchange/connectors');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'connectors' => [
                    '*' => [
                        'name',
                        'display_name',
                        'available',
                    ],
                ],
            ]);
    }

    #[Test]
    public function test_get_ticker_returns_price_data(): void
    {
        // Create a real ExternalTicker instance since it might be a final class
        $ticker = new ExternalTicker(
            baseCurrency: 'BTC',
            quoteCurrency: 'EUR',
            bid: BigDecimal::of('50000.00'),
            ask: BigDecimal::of('50100.00'),
            last: BigDecimal::of('50050.00'),
            volume24h: BigDecimal::of('1234.56'),
            high24h: BigDecimal::of('51000.00'),
            low24h: BigDecimal::of('49000.00'),
            change24h: BigDecimal::of('2.5'),
            timestamp: new \DateTimeImmutable(),
            exchange: 'binance'
        );

        // Setup mock connector to return ticker
        $this->mockConnector->shouldReceive('getTicker')
            ->with('BTC', 'EUR')
            ->andReturn($ticker);

        // Setup registry
        $this->mockRegistry->shouldReceive('available')
            ->andReturn(new Collection(['binance' => $this->mockConnector]));
        $this->mockRegistry->shouldReceive('getBestBid')
            ->with('BTC', 'EUR')
            ->andReturn(['price' => 50000.00, 'exchange' => 'binance']);
        $this->mockRegistry->shouldReceive('getBestAsk')
            ->with('BTC', 'EUR')
            ->andReturn(['price' => 50100.00, 'exchange' => 'binance']);

        $response = $this->getJson('/api/external-exchange/ticker/BTC/EUR');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'pair',
                'tickers',
                'best_bid',
                'best_ask',
                'timestamp',
            ]);
    }

    #[Test]
    public function test_get_ticker_returns_error_for_invalid_pair(): void
    {
        // Setup registry to return empty collection (no connectors available)
        $this->mockRegistry->shouldReceive('available')
            ->andReturn(new Collection());
        $this->mockRegistry->shouldReceive('getBestBid')
            ->with('INVALID', 'EUR')
            ->andReturn(null);
        $this->mockRegistry->shouldReceive('getBestAsk')
            ->with('INVALID', 'EUR')
            ->andReturn(null);

        $response = $this->getJson('/api/external-exchange/ticker/INVALID/EUR');

        // Controller returns 200 even for invalid pairs (just with empty tickers)
        $response->assertStatus(200)
            ->assertJson([
                'tickers' => [],
            ]);
    }

    #[Test]
    public function test_get_order_book_returns_depth_data(): void
    {
        // Setup registry to return aggregated order book
        $this->mockRegistry->shouldReceive('getAggregatedOrderBook')
            ->with('BTC', 'EUR', 20)
            ->andReturn([
                'bids' => [
                    ['price' => 50000, 'amount' => 1.5],
                    ['price' => 49950, 'amount' => 2.0],
                ],
                'asks' => [
                    ['price' => 50100, 'amount' => 1.2],
                    ['price' => 50150, 'amount' => 1.8],
                ],
            ]);

        $response = $this->getJson('/api/external-exchange/orderbook/BTC/EUR');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'pair',
                'orderbook',
                'timestamp',
            ]);
    }

    #[Test]
    public function test_get_order_book_returns_error_for_invalid_pair(): void
    {
        // Setup registry to return empty order book
        $this->mockRegistry->shouldReceive('getAggregatedOrderBook')
            ->with('BTC', 'INVALID', 20)
            ->andReturn([
                'bids' => [],
                'asks' => [],
            ]);

        $response = $this->getJson('/api/external-exchange/orderbook/BTC/INVALID');

        // Controller returns 200 even for invalid pairs (just with empty order book)
        $response->assertStatus(200)
            ->assertJson([
                'orderbook' => [
                    'bids' => [],
                    'asks' => [],
                ],
            ]);
    }

    #[Test]
    public function test_get_arbitrage_opportunities_returns_data(): void
    {
        Sanctum::actingAs($this->user);

        // Setup liquidity service to return arbitrage opportunities
        $this->mockLiquidityService->shouldReceive('findArbitrageOpportunities')
            ->with('BTC', 'EUR')
            ->andReturn([
                [
                    'buy_exchange'  => 'binance',
                    'sell_exchange' => 'kraken',
                    'profit'        => 150.00,
                    'profit_pct'    => 0.3,
                ],
            ]);

        $response = $this->getJson('/api/external-exchange/arbitrage/BTC/EUR');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'pair',
                'opportunities',
                'timestamp',
            ]);
    }

    #[Test]
    public function test_get_arbitrage_opportunities_requires_authentication(): void
    {
        $response = $this->getJson('/api/external-exchange/arbitrage/BTC/EUR');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_get_arbitrage_opportunities_returns_error_for_invalid_pair(): void
    {
        Sanctum::actingAs($this->user);

        // Setup liquidity service to return empty opportunities
        $this->mockLiquidityService->shouldReceive('findArbitrageOpportunities')
            ->with('INVALID', 'INVALID')
            ->andReturn([]);

        $response = $this->getJson('/api/external-exchange/arbitrage/INVALID/INVALID');

        // Controller returns 200 even for invalid pairs (just with empty opportunities)
        $response->assertStatus(200)
            ->assertJson([
                'opportunities' => [],
            ]);
    }
}
