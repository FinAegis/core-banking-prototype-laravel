<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\User;
use App\Models\Account;
use App\Models\AccountBalance;
use App\Domain\Asset\Models\Asset;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AccountTest extends TestCase
{
    public function test_account_factory_creates_account()
    {
        $user = User::factory()->create();
        $account = Account::factory()->forUser($user)->create();
        
        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'user_uuid' => $user->uuid,
        ]);
    }

    public function test_account_has_uuid()
    {
        $account = Account::factory()->create();
        
        $this->assertNotNull($account->uuid);
        $this->assertIsString((string) $account->uuid);
    }

    public function test_account_belongs_to_user()
    {
        $user = User::factory()->create();
        $account = Account::factory()->forUser($user)->create();
        
        $this->assertEquals((string) $user->uuid, (string) $account->user_uuid);
        $this->assertInstanceOf(User::class, $account->user);
    }

    public function test_account_has_balances_relationship()
    {
        $account = Account::factory()->create();
        $asset = Asset::where('code', 'USD')->first();
        $balance = AccountBalance::factory()->create([
            'account_uuid' => $account->uuid,
            'asset_code' => $asset->code
        ]);
        
        $this->assertTrue($account->balances()->exists());
        $this->assertTrue($account->balances->contains($balance));
    }

    public function test_account_fillable_attributes()
    {
        $account = new Account();
        
        // Account uses $guarded = [] which means all attributes are fillable
        $this->assertEmpty($account->getGuarded());
        
        // Create an account with various attributes to ensure they're fillable
        $user = User::factory()->create();
        $testAccount = Account::create([
            'user_uuid' => $user->uuid,
            'name' => 'Test Account',
        ]);
        
        $this->assertEquals((string) $user->uuid, (string) $testAccount->user_uuid);
        $this->assertEquals('Test Account', $testAccount->name);
    }

    public function test_account_default_balance_is_zero()
    {
        $account = Account::factory()->create();
        
        $this->assertEquals(0, $account->balance);
    }

    public function test_account_can_be_frozen()
    {
        $account = Account::factory()->create(['frozen' => true]);
        
        $this->assertTrue($account->frozen);
    }

    public function test_account_default_not_frozen()
    {
        $account = Account::factory()->create();
        
        $this->assertFalse($account->frozen);
    }
}