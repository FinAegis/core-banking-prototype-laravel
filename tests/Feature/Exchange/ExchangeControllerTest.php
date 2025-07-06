<?php

namespace Tests\Feature\Exchange;

use Tests\TestCase;
use App\Models\User;
use App\Domain\Account\Aggregates\Account;
use App\Domain\Account\DataTransferObjects\AccountData;
use App\Domain\Account\Enums\AccountStatus;
use App\Domain\Account\Enums\AccountType;
use App\Domain\Exchange\Aggregates\OrderBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class ExchangeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $accountId;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user
        $this->user = User::factory()->create();

        // Create account for the user
        $this->accountId = (string) Str::uuid();
        Account::create(
            $this->accountId,
            new AccountData(
                userId: $this->user->id,
                name: 'Test Trading Account',
                type: AccountType::PERSONAL,
                status: AccountStatus::ACTIVE,
                metadata: []
            )
        )->deposit('10000.00', 'USD', 'Initial deposit')
         ->deposit('1.00', 'BTC', 'Initial BTC deposit')
         ->persist();

        // Initialize order books
        $btcUsdOrderBookId = OrderBook::generateId('BTC', 'USD');
        OrderBook::retrieve($btcUsdOrderBookId)
            ->initialize($btcUsdOrderBookId, 'BTC', 'USD')
            ->persist();
    }

    public function test_can_access_exchange_index(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('exchange.index'));

        $response->assertStatus(200);
        $response->assertViewIs('exchange.index');
    }

    public function test_can_view_orders(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('exchange.orders'));

        $response->assertStatus(200);
        $response->assertViewIs('exchange.orders');
    }

    public function test_can_view_trades(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('exchange.trades'));

        $response->assertStatus(200);
        $response->assertViewIs('exchange.trades');
    }

    public function test_can_place_buy_order(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('exchange.place-order'), [
                'account_id' => $this->accountId,
                'side' => 'buy',
                'type' => 'limit',
                'base_asset' => 'BTC',
                'quote_asset' => 'USD',
                'amount' => '0.1',
                'price' => '45000.00'
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        
        // Verify order was created
        $this->assertDatabaseHas('orders', [
            'account_id' => $this->accountId,
            'side' => 'buy',
            'base_asset' => 'BTC',
            'quote_asset' => 'USD',
            'amount' => '0.10000000',
            'price' => '45000.00000000'
        ]);
    }

    public function test_can_place_sell_order(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('exchange.place-order'), [
                'account_id' => $this->accountId,
                'side' => 'sell',
                'type' => 'limit',
                'base_asset' => 'BTC',
                'quote_asset' => 'USD',
                'amount' => '0.5',
                'price' => '55000.00'
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        
        // Verify order was created
        $this->assertDatabaseHas('orders', [
            'account_id' => $this->accountId,
            'side' => 'sell',
            'base_asset' => 'BTC',
            'quote_asset' => 'USD',
            'amount' => '0.50000000',
            'price' => '55000.00000000'
        ]);
    }

    public function test_cannot_place_order_with_insufficient_balance(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('exchange.place-order'), [
                'account_id' => $this->accountId,
                'side' => 'buy',
                'type' => 'limit',
                'base_asset' => 'BTC',
                'quote_asset' => 'USD',
                'amount' => '10', // Would need 450,000 USD
                'price' => '45000.00'
            ]);

        $response->assertSessionHasErrors();
    }

    public function test_can_cancel_order(): void
    {
        // First place an order
        $response = $this->actingAs($this->user)
            ->post(route('exchange.place-order'), [
                'account_id' => $this->accountId,
                'side' => 'buy',
                'type' => 'limit',
                'base_asset' => 'BTC',
                'quote_asset' => 'USD',
                'amount' => '0.1',
                'price' => '45000.00'
            ]);

        // Get the order ID from database
        $order = \App\Domain\Exchange\Projections\Order::query()
            ->where('account_id', $this->accountId)
            ->latest()
            ->first();

        // Cancel the order
        $response = $this->actingAs($this->user)
            ->delete(route('exchange.cancel-order', $order->order_id));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        
        // Verify order status is cancelled
        $this->assertDatabaseHas('orders', [
            'order_id' => $order->order_id,
            'status' => 'cancelled'
        ]);
    }

    public function test_guest_cannot_access_exchange(): void
    {
        $response = $this->get(route('exchange.index'));
        $response->assertStatus(200); // Exchange index is public

        $response = $this->get(route('exchange.orders'));
        $response->assertRedirect(route('login'));

        $response = $this->get(route('exchange.trades'));
        $response->assertRedirect(route('login'));
    }

    public function test_can_export_trades(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('exchange.export-trades'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}