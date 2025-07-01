<?php

namespace Tests\Feature\Account;

use Tests\TestCase;
use App\Models\Account;
use App\Models\AccountBalance;
use App\Domain\Asset\Models\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class MultiAssetAccountTest extends TestCase
{
    
    private Account $testAccount;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testAccount = Account::factory()->create();
    }
    
    #[Test]
    public function it_can_read_balance_for_different_assets()
    {
        // Create balances directly since balance manipulation methods are removed
        AccountBalance::create(['account_uuid' => $this->testAccount->uuid, 'asset_code' => 'USD', 'balance' => 10000]); // $100.00
        AccountBalance::create(['account_uuid' => $this->testAccount->uuid, 'asset_code' => 'EUR', 'balance' => 5000]);  // €50.00
        AccountBalance::create(['account_uuid' => $this->testAccount->uuid, 'asset_code' => 'BTC', 'balance' => 100000000]); // 1 BTC
        
        expect($this->testAccount->getBalance('USD'))->toBe(10000);
        expect($this->testAccount->getBalance('EUR'))->toBe(5000);
        expect($this->testAccount->getBalance('BTC'))->toBe(100000000);
        expect($this->testAccount->balances)->toHaveCount(3);
    }
    
    #[Test]
    public function it_returns_zero_balance_for_non_existing_asset()
    {
        expect($this->testAccount->getBalance('GBP'))->toBe(0);
    }
    
    // Balance manipulation tests removed - use event sourcing via WalletService instead
    
    #[Test]
    public function it_can_check_sufficient_balance()
    {
        AccountBalance::create(['account_uuid' => $this->testAccount->uuid, 'asset_code' => 'USD', 'balance' => 5000]); // $50.00
        
        expect($this->testAccount->hasSufficientBalance('USD', 3000))->toBeTrue();
        expect($this->testAccount->hasSufficientBalance('USD', 5000))->toBeTrue();
        expect($this->testAccount->hasSufficientBalance('USD', 6000))->toBeFalse();
        expect($this->testAccount->hasSufficientBalance('EUR', 100))->toBeFalse();
    }
    
    #[Test]
    public function it_can_get_active_balances()
    {
        AccountBalance::create(['account_uuid' => $this->testAccount->uuid, 'asset_code' => 'USD', 'balance' => 10000]);
        AccountBalance::create(['account_uuid' => $this->testAccount->uuid, 'asset_code' => 'EUR', 'balance' => 5000]);
        AccountBalance::create(['account_uuid' => $this->testAccount->uuid, 'asset_code' => 'GBP', 'balance' => 0]); // Zero balance
        
        $activeBalances = $this->testAccount->getActiveBalances();
        
        expect($activeBalances)->toHaveCount(2);
        expect($activeBalances->pluck('asset_code')->toArray())->toContain('USD', 'EUR');
        expect($activeBalances->pluck('asset_code')->toArray())->not->toContain('GBP');
    }
    
    #[Test]
    public function it_maintains_backward_compatibility_with_balance_attribute()
    {
        AccountBalance::create(['account_uuid' => $this->testAccount->uuid, 'asset_code' => 'USD', 'balance' => 12345]);
        
        // The balance attribute should return USD balance
        expect($this->testAccount->balance)->toBe(12345);
        expect($this->testAccount->toArray()['balance'])->toBe(12345);
    }
    
    // Money manipulation methods removed - use event sourcing via WalletService instead
    
    #[Test]
    public function it_can_retrieve_balance_entry()
    {
        expect($this->testAccount->balances()->count())->toBe(0);
        
        AccountBalance::create(['account_uuid' => $this->testAccount->uuid, 'asset_code' => 'EUR', 'balance' => 1000]);
        
        expect($this->testAccount->balances()->count())->toBe(1);
        
        $balance = $this->testAccount->getBalanceForAsset('EUR');
        expect($balance)->toBeInstanceOf(AccountBalance::class);
        expect($balance->asset_code)->toBe('EUR');
        expect($balance->balance)->toBe(1000);
    }
}