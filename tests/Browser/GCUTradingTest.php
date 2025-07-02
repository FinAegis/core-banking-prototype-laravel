<?php

namespace Tests\Browser;

use App\Models\Account;
use App\Models\AccountBalance;
use App\Models\Asset;
use App\Models\BasketAsset;
use App\Models\BasketValue;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class GCUTradingTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected User $user;
    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user and account
        $this->user = User::factory()->create([
            'email' => 'trader@example.com',
            'password' => bcrypt('password'),
        ]);

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
        BasketAsset::factory()->create([
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

        // Give user some balances
        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code' => 'EUR',
            'balance' => 10000.00,
        ]);

        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code' => 'GCU',
            'balance' => 500.00,
        ]);
    }

    /** @test */
    public function user_can_access_gcu_trading_page()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/gcu/trading')
                ->assertSee('Buy & Sell GCU')
                ->assertSee('Current GCU Value')
                ->assertSee('Your GCU Balance')
                ->assertSee('Buy GCU')
                ->assertSee('Sell GCU')
                ->assertSee('Your Trading Limits');
        });
    }

    /** @test */
    public function user_can_get_buy_quote()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/gcu/trading')
                ->type('input[placeholder="0.00"]', '1000')
                ->pause(1000) // Wait for debounced quote update
                ->assertSee('You will receive:')
                ->assertSee('Exchange rate:')
                ->assertSee('Trading fee');
        });
    }

    /** @test */
    public function user_can_buy_gcu()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/gcu/trading')
                ->type('input[placeholder="0.00"]', '1000')
                ->select('select', 'EUR')
                ->pause(1000) // Wait for quote
                ->press('Buy GCU')
                ->pause(2000) // Wait for transaction
                ->assertDialogOpened()
                ->acceptDialog(); // Accept success alert
        });
    }

    /** @test */
    public function user_can_get_sell_quote()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/gcu/trading')
                ->type('input[placeholder="0.0000"]', '100')
                ->pause(1000) // Wait for debounced quote update
                ->within('.bg-white:nth-child(2)', function ($card) {
                    $card->assertSee('You will receive:')
                        ->assertSee('Exchange rate:')
                        ->assertSee('Trading fee');
                });
        });
    }

    /** @test */
    public function user_can_sell_gcu()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/gcu/trading')
                ->within('.bg-white:nth-child(2)', function ($card) {
                    $card->type('input[placeholder="0.0000"]', '100')
                        ->select('select', 'EUR')
                        ->pause(1000) // Wait for quote
                        ->press('Sell GCU');
                })
                ->pause(2000) // Wait for transaction
                ->assertDialogOpened()
                ->acceptDialog(); // Accept success alert
        });
    }

    /** @test */
    public function user_sees_error_for_insufficient_balance()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/gcu/trading')
                ->type('input[placeholder="0.00"]', '20000') // More than available
                ->pause(1000) // Wait for quote
                ->press('Buy GCU')
                ->pause(2000)
                ->assertSee('Insufficient EUR balance');
        });
    }

    /** @test */
    public function user_can_switch_currencies()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/gcu/trading')
                ->select('select', 'USD')
                ->assertSeeIn('.text-gray-500', 'USD')
                ->select('select', 'GBP')
                ->assertSeeIn('.text-gray-500', 'GBP');
        });
    }

    /** @test */
    public function user_sees_trading_limits_progress()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/gcu/trading')
                ->assertSee('Daily Limits')
                ->assertSee('Monthly Limits')
                ->assertSee('KYC Level')
                ->assertPresent('.bg-indigo-600') // Progress bar
                ->assertPresent('.bg-red-600'); // Progress bar
        });
    }

    /** @test */
    public function buy_button_is_disabled_below_minimum()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/gcu/trading')
                ->type('input[placeholder="0.00"]', '50') // Below minimum
                ->assertDisabled('button[type="submit"]');
        });
    }

    /** @test */
    public function sell_button_is_disabled_below_minimum()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/gcu/trading')
                ->within('.bg-white:nth-child(2)', function ($card) {
                    $card->type('input[placeholder="0.0000"]', '5') // Below minimum
                        ->assertDisabled('button[type="submit"]');
                });
        });
    }

    /** @test */
    public function navigation_link_is_visible()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->assertSeeLink('Trade GCU')
                ->clickLink('Trade GCU')
                ->assertPathIs('/gcu/trading');
        });
    }
}