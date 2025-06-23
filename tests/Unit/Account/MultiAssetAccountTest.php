<?php

namespace Tests\Unit\Account;

use Tests\TestCase;
use App\Models\Account;
use App\Models\AccountBalance;
use App\Domain\Asset\Models\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class MultiAssetAccountTest extends TestCase
{
    use RefreshDatabase;
    
    private Account $account;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Run migrations to create assets
        $this->artisan('migrate');
        
        $this->account = Account::factory()->create();
    }
    
    #[Test]
    public function it_can_read_balance_for_different_assets()
    {
        // Create balances directly since balance manipulation methods are removed
        AccountBalance::create(['account_uuid' => $this->account->uuid, 'asset_code' => 'USD', 'balance' => 10000]); // $100.00
        AccountBalance::create(['account_uuid' => $this->account->uuid, 'asset_code' => 'EUR', 'balance' => 5000]);  // €50.00
        AccountBalance::create(['account_uuid' => $this->account->uuid, 'asset_code' => 'BTC', 'balance' => 100000000]); // 1 BTC
        
        expect($this->account->getBalance('USD'))->toBe(10000);
        expect($this->account->getBalance('EUR'))->toBe(5000);
        expect($this->account->getBalance('BTC'))->toBe(100000000);
        expect($this->account->balances)->toHaveCount(3);
    }
    
    #[Test]
    public function it_returns_zero_balance_for_non_existing_asset()
    {
        expect($this->account->getBalance('GBP'))->toBe(0);
    }
    
    // Balance manipulation tests removed - use event sourcing via WalletService instead
    
    #[Test]
    public function it_can_check_sufficient_balance()
    {
        AccountBalance::create(['account_uuid' => $this->account->uuid, 'asset_code' => 'USD', 'balance' => 5000]); // $50.00
        
        expect($this->account->hasSufficientBalance('USD', 3000))->toBeTrue();
        expect($this->account->hasSufficientBalance('USD', 5000))->toBeTrue();
        expect($this->account->hasSufficientBalance('USD', 6000))->toBeFalse();
        expect($this->account->hasSufficientBalance('EUR', 100))->toBeFalse();
    }
    
    #[Test]
    public function it_can_get_active_balances()
    {
        AccountBalance::create(['account_uuid' => $this->account->uuid, 'asset_code' => 'USD', 'balance' => 10000]);
        AccountBalance::create(['account_uuid' => $this->account->uuid, 'asset_code' => 'EUR', 'balance' => 5000]);
        AccountBalance::create(['account_uuid' => $this->account->uuid, 'asset_code' => 'GBP', 'balance' => 0]); // Zero balance
        
        $activeBalances = $this->account->getActiveBalances();
        
        expect($activeBalances)->toHaveCount(2);
        expect($activeBalances->pluck('asset_code')->toArray())->toContain('USD', 'EUR');
        expect($activeBalances->pluck('asset_code')->toArray())->not->toContain('GBP');
    }
    
    #[Test]
    public function it_maintains_backward_compatibility_with_balance_attribute()
    {
        AccountBalance::create(['account_uuid' => $this->account->uuid, 'asset_code' => 'USD', 'balance' => 12345]);
        
        // The balance attribute should return USD balance
        expect($this->account->balance)->toBe(12345);
        expect($this->account->toArray()['balance'])->toBe(12345);
    }
    
    // Money manipulation methods removed - use event sourcing via WalletService instead
    
    #[Test]
    public function it_can_retrieve_balance_entry()
    {
        expect($this->account->balances()->count())->toBe(0);
        
        AccountBalance::create(['account_uuid' => $this->account->uuid, 'asset_code' => 'EUR', 'balance' => 1000]);
        
        expect($this->account->balances()->count())->toBe(1);
        
        $balance = $this->account->getBalanceForAsset('EUR');
        expect($balance)->toBeInstanceOf(AccountBalance::class);
        expect($balance->asset_code)->toBe('EUR');
        expect($balance->balance)->toBe(1000);
    }
}