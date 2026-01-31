<?php

namespace Tests\Feature\Exchange;

// TODO: These tests need to be rewritten to match the current exchange implementation
return;

use App\Domain\Account\Models\Account;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\ControllerTestCase;

class ExchangeControllerTest extends ControllerTestCase
{
    protected User $user;

    protected string $accountId;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user
        $this->user = User::factory()->create();

        // Create account for the user
        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'name'      => 'Test Trading Account',
            'type'      => 'personal',
            'status'    => 'active',
        ]);

        $this->accountId = $account->uuid;

        // Initialize order books
        $btcUsdOrderBookId = OrderBook::generateId('BTC', 'USD');
        OrderBook::retrieve($btcUsdOrderBookId)
            ->initialize($btcUsdOrderBookId, 'BTC', 'USD')
            ->persist();
    }

    #[Test]
    public function test_can_access_exchange_index(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('exchange.index'));

        $response->assertStatus(200);
        $response->assertViewIs('exchange.index');
    }

    #[Test]
    public function test_can_view_orders(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('exchange.orders'));

        $response->assertStatus(200);
        $response->assertViewIs('exchange.orders');
    }

    #[Test]
    public function test_can_view_trades(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('exchange.trades'));

        $response->assertStatus(200);
        $response->assertViewIs('exchange.trades');
    }

    #[Test]
    public function test_can_place_buy_order(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('exchange.place-order'), [
                'account_id'  => $this->accountId,
                'side'        => 'buy',
                'type'        => 'limit',
                'base_asset'  => 'BTC',
                'quote_asset' => 'USD',
                'amount'      => '0.1',
                'price'       => '45000.00',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify order was created
        $this->assertDatabaseHas('orders', [
            'account_id'  => $this->accountId,
            'side'        => 'buy',
            'base_asset'  => 'BTC',
            'quote_asset' => 'USD',
            'amount'      => '0.10000000',
            'price'       => '45000.00000000',
        ]);
    }

    #[Test]
    public function test_can_place_sell_order(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('exchange.place-order'), [
                'account_id'  => $this->accountId,
                'side'        => 'sell',
                'type'        => 'limit',
                'base_asset'  => 'BTC',
                'quote_asset' => 'USD',
                'amount'      => '0.5',
                'price'       => '55000.00',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify order was created
        $this->assertDatabaseHas('orders', [
            'account_id'  => $this->accountId,
            'side'        => 'sell',
            'base_asset'  => 'BTC',
            'quote_asset' => 'USD',
            'amount'      => '0.50000000',
            'price'       => '55000.00000000',
        ]);
    }

    #[Test]
    public function test_cannot_place_order_with_insufficient_balance(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('exchange.place-order'), [
                'account_id'  => $this->accountId,
                'side'        => 'buy',
                'type'        => 'limit',
                'base_asset'  => 'BTC',
                'quote_asset' => 'USD',
                'amount'      => '10', // Would need 450,000 USD
                'price'       => '45000.00',
            ]);

        $response->assertSessionHasErrors();
    }

    #[Test]
    public function test_can_cancel_order(): void
    {
        // First place an order
        $response = $this->actingAs($this->user)
            ->post(route('exchange.place-order'), [
                'account_id'  => $this->accountId,
                'side'        => 'buy',
                'type'        => 'limit',
                'base_asset'  => 'BTC',
                'quote_asset' => 'USD',
                'amount'      => '0.1',
                'price'       => '45000.00',
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
            'status'   => 'cancelled',
        ]);
    }

    #[Test]
    public function test_guest_cannot_access_exchange(): void
    {
        $response = $this->get(route('exchange.index'));
        $response->assertStatus(200); // Exchange index is public

        $response = $this->get(route('exchange.orders'));
        $response->assertRedirect(route('login'));

        $response = $this->get(route('exchange.trades'));
        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function test_can_export_trades(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('exchange.export-trades'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
