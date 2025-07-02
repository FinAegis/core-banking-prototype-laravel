<?php

namespace Tests\Feature\Api\V2;

use App\Models\Account;
use App\Models\AccountBalance;
use App\Models\Asset;
use App\Models\BasketAsset;
use App\Models\BasketValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GCUTradingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Account $account;
    protected BasketAsset $gcu;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user and account
        $this->user = User::factory()->create();
        $this->account = Account::factory()->create([
            'user_id' => $this->user->id,
            'is_primary' => true,
        ]);

        // Create assets
        Asset::factory()->create(['code' => 'EUR', 'type' => 'fiat']);
        Asset::factory()->create(['code' => 'USD', 'type' => 'fiat']);
        Asset::factory()->create(['code' => 'GBP', 'type' => 'fiat']);
        Asset::factory()->create(['code' => 'CHF', 'type' => 'fiat']);
        Asset::factory()->create(['code' => 'GCU', 'type' => 'basket']);

        // Create GCU basket
        $this->gcu = BasketAsset::factory()->create([
            'code' => 'GCU',
            'name' => 'Global Currency Unit',
        ]);

        // Create GCU value
        BasketValue::create([
            'basket_code' => 'GCU',
            'value' => 1.0975,
            'component_values' => [],
            'calculated_at' => now(),
        ]);

        // Give user some EUR balance
        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code' => 'EUR',
            'balance' => 10000.00,
        ]);

        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function can_get_quote_for_buying_gcu()
    {
        $response = $this->getJson('/api/v2/gcu/quote?operation=buy&amount=1000&currency=EUR');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'operation',
                    'input_amount',
                    'input_currency',
                    'output_amount',
                    'output_currency',
                    'exchange_rate',
                    'fee_amount',
                    'fee_currency',
                    'fee_percentage',
                    'quote_valid_until',
                    'minimum_amount',
                    'maximum_amount',
                ],
            ])
            ->assertJsonPath('data.operation', 'buy')
            ->assertJsonPath('data.input_currency', 'EUR')
            ->assertJsonPath('data.output_currency', 'GCU');
    }

    /** @test */
    public function can_get_quote_for_selling_gcu()
    {
        $response = $this->getJson('/api/v2/gcu/quote?operation=sell&amount=100&currency=EUR');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'operation',
                    'input_amount',
                    'input_currency',
                    'output_amount',
                    'output_currency',
                    'exchange_rate',
                    'fee_amount',
                    'fee_currency',
                    'fee_percentage',
                    'quote_valid_until',
                    'minimum_amount',
                    'maximum_amount',
                ],
            ])
            ->assertJsonPath('data.operation', 'sell')
            ->assertJsonPath('data.input_currency', 'GCU')
            ->assertJsonPath('data.output_currency', 'EUR');
    }

    /** @test */
    public function can_buy_gcu_with_eur()
    {
        $response = $this->postJson('/api/v2/gcu/buy', [
            'amount' => 1000,
            'currency' => 'EUR',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'transaction_id',
                    'account_uuid',
                    'spent_amount',
                    'spent_currency',
                    'received_amount',
                    'received_currency',
                    'exchange_rate',
                    'fee_amount',
                    'fee_currency',
                    'new_gcu_balance',
                    'timestamp',
                ],
                'message',
            ])
            ->assertJsonPath('data.spent_currency', 'EUR')
            ->assertJsonPath('data.received_currency', 'GCU');

        // Verify balances were updated
        $this->assertDatabaseHas('account_balances', [
            'account_uuid' => $this->account->uuid,
            'asset_code' => 'EUR',
        ]);

        $this->assertDatabaseHas('account_balances', [
            'account_uuid' => $this->account->uuid,
            'asset_code' => 'GCU',
        ]);
    }

    /** @test */
    public function cannot_buy_gcu_with_insufficient_balance()
    {
        $response = $this->postJson('/api/v2/gcu/buy', [
            'amount' => 20000, // More than available balance
            'currency' => 'EUR',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'Insufficient Balance');
    }

    /** @test */
    public function cannot_buy_gcu_below_minimum_amount()
    {
        $response = $this->postJson('/api/v2/gcu/buy', [
            'amount' => 50, // Below minimum of 100
            'currency' => 'EUR',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function can_sell_gcu_for_eur()
    {
        // Give user some GCU balance
        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code' => 'GCU',
            'balance' => 1000.00,
        ]);

        $response = $this->postJson('/api/v2/gcu/sell', [
            'amount' => 100,
            'currency' => 'EUR',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'transaction_id',
                    'account_uuid',
                    'sold_amount',
                    'sold_currency',
                    'received_amount',
                    'received_currency',
                    'exchange_rate',
                    'fee_amount',
                    'fee_currency',
                    'new_gcu_balance',
                    'timestamp',
                ],
                'message',
            ])
            ->assertJsonPath('data.sold_currency', 'GCU')
            ->assertJsonPath('data.received_currency', 'EUR');
    }

    /** @test */
    public function cannot_sell_gcu_with_insufficient_balance()
    {
        // Give user minimal GCU balance
        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code' => 'GCU',
            'balance' => 5.00,
        ]);

        $response = $this->postJson('/api/v2/gcu/sell', [
            'amount' => 100, // More than available
            'currency' => 'EUR',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'Insufficient Balance');
    }

    /** @test */
    public function can_get_trading_limits()
    {
        $response = $this->getJson('/api/v2/gcu/trading-limits');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'daily_buy_limit',
                    'daily_sell_limit',
                    'daily_buy_used',
                    'daily_sell_used',
                    'monthly_buy_limit',
                    'monthly_sell_limit',
                    'monthly_buy_used',
                    'monthly_sell_used',
                    'minimum_buy_amount',
                    'minimum_sell_amount',
                    'kyc_level',
                    'limits_currency',
                ],
            ]);
    }

    /** @test */
    public function cannot_buy_gcu_with_frozen_account()
    {
        $this->account->update(['frozen_at' => now()]);

        $response = $this->postJson('/api/v2/gcu/buy', [
            'amount' => 1000,
            'currency' => 'EUR',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'Account Frozen');
    }

    /** @test */
    public function cannot_sell_gcu_with_frozen_account()
    {
        $this->account->update(['frozen_at' => now()]);

        // Give user some GCU balance
        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code' => 'GCU',
            'balance' => 1000.00,
        ]);

        $response = $this->postJson('/api/v2/gcu/sell', [
            'amount' => 100,
            'currency' => 'EUR',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'Account Frozen');
    }

    /** @test */
    public function quote_requires_valid_parameters()
    {
        $response = $this->getJson('/api/v2/gcu/quote');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['operation', 'amount', 'currency']);
    }

    /** @test */
    public function trading_endpoints_require_authentication()
    {
        Sanctum::actingAs(null); // Remove authentication

        $this->postJson('/api/v2/gcu/buy', ['amount' => 1000, 'currency' => 'EUR'])
            ->assertUnauthorized();

        $this->postJson('/api/v2/gcu/sell', ['amount' => 100, 'currency' => 'EUR'])
            ->assertUnauthorized();

        $this->getJson('/api/v2/gcu/quote?operation=buy&amount=1000&currency=EUR')
            ->assertUnauthorized();

        $this->getJson('/api/v2/gcu/trading-limits')
            ->assertUnauthorized();
    }

    /** @test */
    public function can_buy_gcu_with_different_currencies()
    {
        $currencies = ['USD', 'GBP', 'CHF'];

        foreach ($currencies as $currency) {
            // Give user balance in this currency
            AccountBalance::create([
                'account_uuid' => $this->account->uuid,
                'asset_code' => $currency,
                'balance' => 10000.00,
            ]);

            $response = $this->postJson('/api/v2/gcu/buy', [
                'amount' => 1000,
                'currency' => $currency,
            ]);

            $response->assertOk()
                ->assertJsonPath('data.spent_currency', $currency)
                ->assertJsonPath('data.received_currency', 'GCU');
        }
    }
}