<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Domain\Account\Models\Account;
use App\Domain\Exchange\Projections\Order;
use App\Domain\Exchange\Projections\Trade;
use App\Domain\Exchange\Services\ExchangeService;
use App\Models\User;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\ControllerTestCase;

class ExchangeControllerTest extends ControllerTestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Account $account;

    protected ExchangeService $exchangeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'balance'   => 100000, // 1000.00
        ]);

        // The account is automatically associated through the user_uuid foreign key
        // No need to manually associate

        $this->exchangeService = Mockery::mock(ExchangeService::class);
        $this->app->instance(ExchangeService::class, $this->exchangeService);
    }

    #[Test]
    public function test_place_order_with_valid_market_order(): void
    {
        Sanctum::actingAs($this->user);

        $this->exchangeService->shouldReceive('placeOrder')
            ->once()
            ->with(
                $this->account->id,
                'buy',
                'market',
                'BTC',
                'EUR',
                0.01,
                null,
                null,
                Mockery::any()
            )
            ->andReturn([
                'success'  => true,
                'order_id' => 'order-123',
                'message'  => 'Order placed successfully',
            ]);

        $response = $this->postJson('/api/exchange/orders', [
            'type'           => 'buy',
            'order_type'     => 'market',
            'base_currency'  => 'BTC',
            'quote_currency' => 'EUR',
            'amount'         => 0.01,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success'  => true,
                'order_id' => 'order-123',
                'message'  => 'Order placed successfully',
            ]);
    }

    #[Test]
    public function test_place_order_with_valid_limit_order(): void
    {
        Sanctum::actingAs($this->user);

        $this->exchangeService->shouldReceive('placeOrder')
            ->once()
            ->with(
                $this->account->id,
                'sell',
                'limit',
                'BTC',
                'EUR',
                0.5,
                50000,
                null,
                Mockery::any()
            )
            ->andReturn([
                'success'  => true,
                'order_id' => 'order-456',
                'message'  => 'Limit order placed successfully',
            ]);

        $response = $this->postJson('/api/exchange/orders', [
            'type'           => 'sell',
            'order_type'     => 'limit',
            'base_currency'  => 'BTC',
            'quote_currency' => 'EUR',
            'amount'         => 0.5,
            'price'          => 50000,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success'  => true,
                'order_id' => 'order-456',
            ]);
    }

    #[Test]
    public function test_place_order_with_stop_price(): void
    {
        Sanctum::actingAs($this->user);

        $this->exchangeService->shouldReceive('placeOrder')
            ->once()
            ->with(
                $this->account->id,
                'sell',
                'limit',
                'BTC',
                'EUR',
                0.1,
                50000,
                49000,
                Mockery::any()
            )
            ->andReturn(['success' => true, 'order_id' => 'order-789']);

        $response = $this->postJson('/api/exchange/orders', [
            'type'           => 'sell',
            'order_type'     => 'limit',
            'base_currency'  => 'BTC',
            'quote_currency' => 'EUR',
            'amount'         => 0.1,
            'price'          => 50000,
            'stop_price'     => 49000,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    #[Test]
    public function test_place_order_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/exchange/orders', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'order_type', 'base_currency', 'quote_currency', 'amount']);
    }

    #[Test]
    public function test_place_order_validates_limit_order_requires_price(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/exchange/orders', [
            'type'           => 'buy',
            'order_type'     => 'limit',
            'base_currency'  => 'BTC',
            'quote_currency' => 'EUR',
            'amount'         => 0.1,
            // Missing price for limit order
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['price']);
    }

    #[Test]
    public function test_place_order_validates_currency_format(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/exchange/orders', [
            'type'           => 'buy',
            'order_type'     => 'market',
            'base_currency'  => 'BITCOIN', // Too long
            'quote_currency' => 'EU', // Too short
            'amount'         => 0.1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['base_currency', 'quote_currency']);
    }

    #[Test]
    public function test_place_order_fails_without_account(): void
    {
        // Create user without account
        $userWithoutAccount = User::factory()->create();
        Sanctum::actingAs($userWithoutAccount);

        $response = $this->postJson('/api/exchange/orders', [
            'type'           => 'buy',
            'order_type'     => 'market',
            'base_currency'  => 'BTC',
            'quote_currency' => 'EUR',
            'amount'         => 0.1,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error'   => 'Account not found. Please complete your account setup.',
            ]);
    }

    #[Test]
    public function test_place_order_handles_service_exception(): void
    {
        Sanctum::actingAs($this->user);

        $this->exchangeService->shouldReceive('placeOrder')
            ->once()
            ->andThrow(new Exception('Insufficient balance'));

        $response = $this->postJson('/api/exchange/orders', [
            'type'           => 'buy',
            'order_type'     => 'market',
            'base_currency'  => 'BTC',
            'quote_currency' => 'EUR',
            'amount'         => 100,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error'   => 'Insufficient balance',
            ]);
    }

    #[Test]
    public function test_place_order_requires_authentication(): void
    {
        $response = $this->postJson('/api/exchange/orders', [
            'type'           => 'buy',
            'order_type'     => 'market',
            'base_currency'  => 'BTC',
            'quote_currency' => 'EUR',
            'amount'         => 0.1,
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function test_cancel_order_successfully(): void
    {
        Sanctum::actingAs($this->user);

        // Create an order for the user
        $order = Order::create([
            'order_id'       => 'order-123',
            'account_id'     => $this->account->id,
            'type'           => 'buy',
            'order_type'     => 'limit',
            'status'         => 'open',
            'base_currency'  => 'BTC',
            'quote_currency' => 'EUR',
            'amount'         => 0.1,
            'filled_amount'  => 0,
            'price'          => 50000,
        ]);

        $this->exchangeService->shouldReceive('cancelOrder')
            ->once()
            ->with('order-123')
            ->andReturn([
                'success' => true,
                'message' => 'Order cancelled successfully',
            ]);

        $response = $this->deleteJson('/api/exchange/orders/order-123');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Order cancelled successfully',
            ]);
    }

    #[Test]
    public function test_cancel_order_returns_404_for_non_existent_order(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->deleteJson('/api/exchange/orders/non-existent-order');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error'   => 'Order not found',
            ]);
    }

    #[Test]
    public function test_cancel_order_prevents_cancelling_other_users_orders(): void
    {
        Sanctum::actingAs($this->user);

        // Create an order for another user
        $otherAccount = Account::factory()->create();
        Order::create([
            'order_id'       => 'other-user-order',
            'account_id'     => $otherAccount->id,
            'type'           => 'buy',
            'order_type'     => 'market',
            'status'         => 'open',
            'base_currency'  => 'BTC',
            'quote_currency' => 'EUR',
            'amount'         => 0.1,
            'filled_amount'  => 0,
        ]);

        $response = $this->deleteJson('/api/exchange/orders/other-user-order');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error'   => 'Order not found',
            ]);
    }

    #[Test]
    public function test_cancel_order_requires_authentication(): void
    {
        $response = $this->deleteJson('/api/exchange/orders/order-123');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_get_orders_returns_user_orders(): void
    {
        Sanctum::actingAs($this->user);

        // Create some orders
        Order::create([
            'order_id'       => 'order-1',
            'account_id'     => $this->account->id,
            'type'           => 'buy',
            'order_type'     => 'market',
            'status'         => 'filled',
            'base_currency'  => 'BTC',
            'quote_currency' => 'EUR',
            'amount'         => 0.1,
            'filled_amount'  => 0.1,
            'price'          => null,
        ]);

        Order::create([
            'order_id'       => 'order-2',
            'account_id'     => $this->account->id,
            'type'           => 'sell',
            'order_type'     => 'limit',
            'status'         => 'open',
            'base_currency'  => 'BTC',
            'quote_currency' => 'EUR',
            'amount'         => 0.5,
            'filled_amount'  => 0,
            'price'          => 55000,
        ]);

        $response = $this->getJson('/api/exchange/orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'order_id',
                        'type',
                        'order_type',
                        'status',
                        'base_currency',
                        'quote_currency',
                        'amount',
                        'filled_amount',
                        'price',
                    ],
                ],
                'current_page',
                'last_page',
                'per_page',
                'total',
            ]);
    }

    #[Test]
    public function test_get_orders_filters_by_status(): void
    {
        Sanctum::actingAs($this->user);

        // Create orders with different statuses
        Order::create([
            'order_id'       => 'open-order',
            'account_id'     => $this->account->id,
            'type'           => 'buy',
            'order_type'     => 'limit',
            'status'         => 'open',
            'base_currency'  => 'BTC',
            'quote_currency' => 'EUR',
            'amount'         => 0.1,
            'filled_amount'  => 0,
            'price'          => 50000,
        ]);

        Order::create([
            'order_id'       => 'filled-order',
            'account_id'     => $this->account->id,
            'type'           => 'sell',
            'order_type'     => 'market',
            'status'         => 'filled',
            'base_currency'  => 'BTC',
            'quote_currency' => 'EUR',
            'amount'         => 0.2,
            'filled_amount'  => 0.2,
        ]);

        $response = $this->getJson('/api/exchange/orders?status=open');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('open', $data[0]['status']);
    }

    #[Test]
    public function test_get_orders_filters_by_trading_pair(): void
    {
        Sanctum::actingAs($this->user);

        Order::create([
            'order_id'       => 'btc-eur-order',
            'account_id'     => $this->account->id,
            'type'           => 'buy',
            'order_type'     => 'limit',
            'status'         => 'open',
            'base_currency'  => 'BTC',
            'quote_currency' => 'EUR',
            'amount'         => 0.1,
            'filled_amount'  => 0,
            'price'          => 50000,
        ]);

        Order::create([
            'order_id'       => 'eth-eur-order',
            'account_id'     => $this->account->id,
            'type'           => 'buy',
            'order_type'     => 'limit',
            'status'         => 'open',
            'base_currency'  => 'ETH',
            'quote_currency' => 'EUR',
            'amount'         => 1,
            'filled_amount'  => 0,
            'price'          => 3000,
        ]);

        $response = $this->getJson('/api/exchange/orders?base_currency=BTC&quote_currency=EUR');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('BTC', $data[0]['base_currency']);
    }

    #[Test]
    public function test_get_orders_requires_authentication(): void
    {
        $response = $this->getJson('/api/exchange/orders');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_get_trades_returns_user_trades(): void
    {
        Sanctum::actingAs($this->user);

        // Create some trades
        Trade::create([
            'trade_id'          => 'trade-1',
            'buy_order_id'      => 'order-1',
            'sell_order_id'     => 'order-2',
            'buyer_account_id'  => $this->account->id,
            'seller_account_id' => 999, // Different account
            'base_currency'     => 'BTC',
            'quote_currency'    => 'EUR',
            'amount'            => 0.1,
            'price'             => 50000,
            'value'             => 5000,
            'maker_fee'         => 10,
            'taker_fee'         => 15,
            'maker_side'        => 'buy',
        ]);

        $response = $this->getJson('/api/exchange/trades');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'trade_id',
                        'buy_order_id',
                        'sell_order_id',
                        'buyer_account_id',
                        'seller_account_id',
                        'base_currency',
                        'quote_currency',
                        'amount',
                        'price',
                        'value',
                        'maker_fee',
                        'taker_fee',
                        'maker_side',
                    ],
                ],
            ]);
    }

    #[Test]
    public function test_get_trades_filters_by_trading_pair(): void
    {
        Sanctum::actingAs($this->user);

        Trade::create([
            'trade_id'          => 'btc-trade',
            'buy_order_id'      => 'order-1',
            'sell_order_id'     => 'order-2',
            'buyer_account_id'  => $this->account->id,
            'seller_account_id' => 999,
            'base_currency'     => 'BTC',
            'quote_currency'    => 'EUR',
            'amount'            => 0.1,
            'price'             => 50000,
            'value'             => 5000,
            'maker_fee'         => 10,
            'taker_fee'         => 15,
            'maker_side'        => 'buy',
        ]);

        Trade::create([
            'trade_id'          => 'eth-trade',
            'buy_order_id'      => 'order-3',
            'sell_order_id'     => 'order-4',
            'buyer_account_id'  => $this->account->id,
            'seller_account_id' => 999,
            'base_currency'     => 'ETH',
            'quote_currency'    => 'EUR',
            'amount'            => 1,
            'price'             => 3000,
            'value'             => 3000,
            'maker_fee'         => 6,
            'taker_fee'         => 9,
            'maker_side'        => 'sell',
        ]);

        $response = $this->getJson('/api/exchange/trades?base_currency=BTC&quote_currency=EUR');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('BTC', $data[0]['base_currency']);
    }

    #[Test]
    public function test_get_trades_requires_authentication(): void
    {
        $response = $this->getJson('/api/exchange/trades');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_get_order_book_returns_order_book_data(): void
    {
        $this->exchangeService->shouldReceive('getOrderBook')
            ->once()
            ->with('BTC', 'EUR', 20)
            ->andReturn([
                'bids' => [
                    ['price' => 49900, 'amount' => 0.5],
                    ['price' => 49800, 'amount' => 1.0],
                ],
                'asks' => [
                    ['price' => 50100, 'amount' => 0.3],
                    ['price' => 50200, 'amount' => 0.8],
                ],
                'spread'    => 200,
                'mid_price' => 50000,
            ]);

        $response = $this->getJson('/api/exchange/orderbook/BTC/EUR');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'bids' => [
                    '*' => ['price', 'amount'],
                ],
                'asks' => [
                    '*' => ['price', 'amount'],
                ],
                'spread',
                'mid_price',
            ]);
    }

    #[Test]
    public function test_get_order_book_with_custom_depth(): void
    {
        $this->exchangeService->shouldReceive('getOrderBook')
            ->once()
            ->with('BTC', 'EUR', 50)
            ->andReturn(['bids' => [], 'asks' => []]);

        $response = $this->getJson('/api/exchange/orderbook/BTC/EUR?depth=50');

        $response->assertStatus(200);
    }

    #[Test]
    public function test_get_order_book_limits_max_depth(): void
    {
        $this->exchangeService->shouldReceive('getOrderBook')
            ->once()
            ->with('BTC', 'EUR', 100) // Max is 100
            ->andReturn(['bids' => [], 'asks' => []]);

        $response = $this->getJson('/api/exchange/orderbook/BTC/EUR?depth=200');

        $response->assertStatus(200);
    }

    #[Test]
    public function test_get_order_book_does_not_require_authentication(): void
    {
        $this->exchangeService->shouldReceive('getOrderBook')
            ->once()
            ->andReturn(['bids' => [], 'asks' => []]);

        $response = $this->getJson('/api/exchange/orderbook/BTC/EUR');

        $response->assertStatus(200);
    }

    #[Test]
    public function test_get_markets_returns_market_data(): void
    {
        // Mock for BTC/EUR
        $this->exchangeService->shouldReceive('getMarketData')
            ->once()
            ->with('BTC', 'EUR')
            ->andReturn([
                'pair'                  => 'BTC/EUR',
                'last_price'            => 50000,
                'volume_24h'            => 1250.5,
                'change_24h_percentage' => 2.5,
                'high_24h'              => 51000,
                'low_24h'               => 49000,
            ]);

        // Mock for ETH/EUR
        $this->exchangeService->shouldReceive('getMarketData')
            ->once()
            ->with('ETH', 'EUR')
            ->andReturn([
                'pair'                  => 'ETH/EUR',
                'last_price'            => 3000,
                'volume_24h'            => 5000,
                'change_24h_percentage' => -1.2,
                'high_24h'              => 3100,
                'low_24h'               => 2950,
            ]);

        $response = $this->getJson('/api/exchange/markets');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'pair',
                        'base_currency',
                        'quote_currency',
                        'last_price',
                        'volume_24h',
                        'change_24h_percentage',
                        'high_24h',
                        'low_24h',
                    ],
                ],
            ])
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function test_get_markets_does_not_require_authentication(): void
    {
        // Mock for BTC/EUR
        $this->exchangeService->shouldReceive('getMarketData')
            ->once()
            ->with('BTC', 'EUR')
            ->andReturn(['pair' => 'BTC/EUR', 'last_price' => 50000]);

        // Mock for ETH/EUR
        $this->exchangeService->shouldReceive('getMarketData')
            ->once()
            ->with('ETH', 'EUR')
            ->andReturn(['pair' => 'ETH/EUR', 'last_price' => 3000]);

        $response = $this->getJson('/api/exchange/markets');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
