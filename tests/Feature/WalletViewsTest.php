<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Account;
use App\Models\AccountBalance;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Models\ExchangeRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletViewsTest extends TestCase
{
    use RefreshDatabase;

    protected User $testUser;
    protected Account $testAccount;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user and account
        $this->testUser = User::factory()->withPersonalTeam()->create();
        $this->testAccount = Account::factory()->zeroBalance()->create([
            'user_uuid' => $this->testUser->uuid,
        ]);
        
        // Create assets
        Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'GBP'], ['name' => 'British Pound', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'BTC'], ['name' => 'Bitcoin', 'type' => 'crypto', 'precision' => 8, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'GCU'], ['name' => 'Global Currency Unit', 'type' => 'basket', 'precision' => 2, 'is_active' => true]);
    }

    /** @test */
    public function deposit_view_displays_all_active_assets()
    {
        $response = $this->actingAs($this->testUser)
            ->get(route('wallet.deposit'));
            
        $response->assertOk();
        $response->assertSee('US Dollar (USD)');
        $response->assertSee('Euro (EUR)');
        $response->assertSee('British Pound (GBP)');
        $response->assertSee('Bitcoin (BTC)');
        $response->assertSee('Global Currency Unit (GCU)');
    }

    /** @test */
    public function deposit_view_shows_deposit_methods()
    {
        $response = $this->actingAs($this->testUser)
            ->get(route('wallet.deposit'));
            
        $response->assertOk();
        $response->assertSee('Bank Transfer (ACH/SEPA) - No fees');
        $response->assertSee('Wire Transfer - Available for large deposits');
        $response->assertSee('Card Payment - 2.9% + $0.30 fee');
    }

    /** @test */
    public function withdraw_view_shows_available_balances()
    {
        // Create some balances
        AccountBalance::updateOrCreate(
            [
                'account_uuid' => $this->testAccount->uuid,
                'asset_code' => 'USD',
            ],
            [
                'balance' => 10000, // $100.00
            ]
        );
        
        AccountBalance::updateOrCreate(
            [
                'account_uuid' => $this->testAccount->uuid,
                'asset_code' => 'EUR',
            ],
            [
                'balance' => 5000, // €50.00
            ]
        );
        
        $response = $this->actingAs($this->testUser)
            ->get(route('wallet.withdraw'));
            
        $response->assertOk();
        $response->assertSee('US Dollar (USD) - Balance: 100.00');
        $response->assertSee('Euro (EUR) - Balance: 50.00');
    }

    /** @test */
    public function withdraw_view_shows_empty_state_when_no_balance()
    {
        $response = $this->actingAs($this->testUser)
            ->get(route('wallet.withdraw'));
            
        $response->assertOk();
        $response->assertSee('No available balance to withdraw');
        $response->assertSee('Deposit funds first');
    }

    /** @test */
    public function transfer_view_shows_available_balances()
    {
        AccountBalance::updateOrCreate(
            [
                'account_uuid' => $this->testAccount->uuid,
                'asset_code' => 'USD',
            ],
            [
                'balance' => 20000, // $200.00
            ]
        );
        
        $response = $this->actingAs($this->testUser)
            ->get(route('wallet.transfer'));
            
        $response->assertOk();
        $response->assertSee('US Dollar (USD) - Balance: 200.00');
        $response->assertSee('Recipient Account ID');
    }

    /** @test */
    public function transfer_view_shows_transfer_information()
    {
        AccountBalance::updateOrCreate(
            [
                'account_uuid' => $this->testAccount->uuid,
                'asset_code' => 'USD',
            ],
            [
                'balance' => 20000,
            ]
        );
        
        $response = $this->actingAs($this->testUser)
            ->get(route('wallet.transfer'));
            
        $response->assertOk();
        $response->assertSee('Instant transfer to FinAegis accounts');
        $response->assertSee('No fees for internal transfers');
    }

    /** @test */
    public function convert_view_shows_from_currencies_with_balance()
    {
        AccountBalance::updateOrCreate(
            [
                'account_uuid' => $this->testAccount->uuid,
                'asset_code' => 'USD',
            ],
            [
                'balance' => 10000,
            ]
        );
        
        AccountBalance::updateOrCreate(
            [
                'account_uuid' => $this->testAccount->uuid,
                'asset_code' => 'EUR',
            ],
            [
                'balance' => 5000,
            ]
        );
        
        $response = $this->actingAs($this->testUser)
            ->get(route('wallet.convert'));
            
        $response->assertOk();
        $response->assertSee('US Dollar (USD) - Balance: 100.00');
        $response->assertSee('Euro (EUR) - Balance: 50.00');
    }

    /** @test */
    public function convert_view_shows_all_available_target_currencies()
    {
        AccountBalance::updateOrCreate(
            [
                'account_uuid' => $this->testAccount->uuid,
                'asset_code' => 'USD',
            ],
            [
                'balance' => 10000,
            ]
        );
        
        $response = $this->actingAs($this->testUser)
            ->get(route('wallet.convert'));
            
        $response->assertOk();
        $response->assertSee('To Currency');
        // Should see all assets as target options
        $response->assertSee('option value="USD"', false);
        $response->assertSee('option value="EUR"', false);
        $response->assertSee('option value="GBP"', false);
        $response->assertSee('option value="BTC"', false);
        $response->assertSee('option value="GCU"', false);
    }

    /** @test */
    public function convert_view_includes_exchange_rate_preview()
    {
        AccountBalance::updateOrCreate(
            [
                'account_uuid' => $this->testAccount->uuid,
                'asset_code' => 'USD',
            ],
            [
                'balance' => 10000,
            ]
        );
        
        ExchangeRate::create([
            'from_asset_code' => 'USD',
            'to_asset_code' => 'EUR',
            'rate' => 0.92,
            'source' => 'manual',
            'valid_at' => now(),
        ]);
        
        $response = $this->actingAs($this->testUser)
            ->get(route('wallet.convert'));
            
        $response->assertOk();
        $response->assertSee('Exchange Rate');
        $response->assertSee('You will receive approximately');
        $response->assertSee('Rate includes 0.1% conversion fee');
    }

    /** @test */
    public function all_wallet_views_have_cancel_buttons()
    {
        // Create balance for views that need it
        AccountBalance::updateOrCreate(
            [
                'account_uuid' => $this->testAccount->uuid,
                'asset_code' => 'USD',
            ],
            [
                'balance' => 10000,
            ]
        );
        
        $routes = [
            'wallet.deposit',
            'wallet.withdraw',
            'wallet.transfer',
            'wallet.convert',
        ];
        
        foreach ($routes as $route) {
            $response = $this->actingAs($this->testUser)->get(route($route));
            $response->assertOk();
            $response->assertSee('Cancel');
            $response->assertSee(route('dashboard'));
        }
    }

    /** @test */
    public function wallet_views_show_demo_environment_notice()
    {
        $response = $this->actingAs($this->testUser)
            ->get(route('wallet.deposit'));
            
        $response->assertOk();
        $response->assertSee('Demo Environment');
        $response->assertSee('This is a demo environment');
    }

    /** @test */
    public function withdraw_view_shows_withdrawal_limits()
    {
        AccountBalance::updateOrCreate(
            [
                'account_uuid' => $this->testAccount->uuid,
                'asset_code' => 'USD',
            ],
            [
                'balance' => 10000,
            ]
        );
        
        $response = $this->actingAs($this->testUser)
            ->get(route('wallet.withdraw'));
            
        $response->assertOk();
        $response->assertSee('Withdrawal Limits');
        $response->assertSee('Daily limit: $10,000');
        $response->assertSee('Monthly limit: $50,000');
        $response->assertSee('Minimum withdrawal: $10.00');
    }

    /** @test */
    public function views_include_javascript_for_dynamic_updates()
    {
        AccountBalance::updateOrCreate(
            [
                'account_uuid' => $this->testAccount->uuid,
                'asset_code' => 'USD',
            ],
            [
                'balance' => 10000,
            ]
        );
        
        // Test deposit view has currency symbol update
        $response = $this->actingAs($this->testUser)->get(route('wallet.deposit'));
        $response->assertSee("document.getElementById('asset_code').addEventListener('change'", false);
        
        // Test withdraw view has balance update
        $response = $this->actingAs($this->testUser)->get(route('wallet.withdraw'));
        $response->assertSee('function updateBalance()');
        $response->assertSee('Available:');
        
        // Test transfer view has balance update
        $response = $this->actingAs($this->testUser)->get(route('wallet.transfer'));
        $response->assertSee('function updateBalance()');
        
        // Test convert view has exchange rate update
        $response = $this->actingAs($this->testUser)->get(route('wallet.convert'));
        $response->assertSee('function updateExchangeRate()');
    }

    /** @test */
    public function deposit_view_handles_validation_errors()
    {
        // Submit with validation errors
        $response = $this->actingAs($this->testUser)
            ->post(route('wallet.deposit.store'), [
                'account_uuid' => $this->testAccount->uuid,
                'amount' => -50, // Invalid negative amount
                'asset_code' => 'USD',
            ]);
            
        $response->assertSessionHasErrors(['amount']);
        
        // Check error display on redirect
        $response = $this->actingAs($this->testUser)
            ->get(route('wallet.deposit'));
            
        $response->assertOk();
        $response->assertSee('class="mb-4 bg-red-50 border border-red-200 text-red-700', false);
    }
}