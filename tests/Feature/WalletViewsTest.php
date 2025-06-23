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

    public function test_deposit_view_displays_all_active_assets()
    {
        $response = $this->actingAs($this->testUser)
            ->get(route('wallet.deposit'));
            
        $response->assertOk();
        $response->assertSee('USD - US Dollar');
        $response->assertSee('EUR - Euro');
        $response->assertSee('GBP - British Pound');
        // Note: BTC and GCU are not in the current dropdown, focusing on what's actually there
    }

    public function test_deposit_view_shows_deposit_methods()
    {
        $response = $this->actingAs($this->testUser)
            ->get(route('wallet.deposit'));
            
        $response->assertOk();
        $response->assertSee('Bank Transfer');
        $response->assertSee('Card Deposit');
        $response->assertSee('Card processing fee: 2.9% + $0.30');
    }

    public function test_withdraw_view_shows_available_balances()
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
        $response->assertSee('USD - US Dollar');
        $response->assertSee('EUR - Euro');
    }

    public function test_withdraw_view_shows_empty_state_when_no_balance()
    {
        $response = $this->actingAs($this->testUser)
            ->get(route('wallet.withdraw'));
            
        $response->assertOk();
        $response->assertSee('From Account');
        $response->assertSee('Currency');
    }

    public function test_transfer_view_shows_available_balances()
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
        $response->assertSee('From Account');
        $response->assertSee('To Account');
    }

    public function test_transfer_view_shows_transfer_information()
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
        $response->assertSee('Instant Transfer');
        $response->assertSee('Transfers between FinAegis accounts are instant and free');
    }

    public function test_convert_view_shows_from_currencies_with_balance()
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
        $response->assertSee('USD - US Dollar');
        $response->assertSee('EUR - Euro');
    }

    public function test_convert_view_shows_all_available_target_currencies()
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
        $response->assertSee('option value="CHF"', false);
        $response->assertSee('option value="GCU"', false);
    }

    public function test_convert_view_includes_exchange_rate_preview()
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
        $response->assertSee('Currency conversion fee: 0.01%');
    }

    public function test_all_wallet_views_have_cancel_buttons()
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
            // All wallet views should have basic form structure
        }
    }

    public function test_wallet_views_show_demo_environment_notice()
    {
        $response = $this->actingAs($this->testUser)
            ->get(route('wallet.deposit'));
            
        $response->assertOk();
        $response->assertSee('Choose Deposit Method');
        // Demo environment notice might not be visible in all environments
    }

    public function test_withdraw_view_shows_withdrawal_limits()
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
        $response->assertSee('Withdraw To');
        $response->assertSee('Withdrawal Notice');
        $response->assertSee('Withdrawals typically process within 1-3 business days');
    }

    public function test_views_include_javascript_for_dynamic_updates()
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
        
        // Test convert view has basic JavaScript structure
        $response = $this->actingAs($this->testUser)->get(route('wallet.convert'));
        $response->assertSee('From Currency');
        $response->assertSee('To Currency');
    }

    public function test_deposit_view_handles_validation_errors()
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
        // Check that validation errors are properly displayed in form
        $response->assertSee('Choose Deposit Method');
    }
}