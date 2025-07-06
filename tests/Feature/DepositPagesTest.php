<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DepositPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user with team
        $this->user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($this->user);
    }

    public function test_deposit_page_loads_without_errors()
    {
        $response = $this->get('/wallet/deposit');
        
        $response->assertStatus(200);
        $response->assertSee('Choose Deposit Method');
        $response->assertSee('Bank Transfer');
        $response->assertSee('Card Deposit');
        
        // Should show account creation button if no account exists
        $response->assertSee('Account Setup Required');
        $response->assertSee('Create Account Now');
        $response->assertDontSee('refresh the page');
    }
    
    public function test_crypto_deposit_page_loads_without_errors()
    {
        $response = $this->get('/wallet/deposit/crypto');
        
        $response->assertStatus(200);
        $response->assertSee('Cryptocurrency Deposit');
        $response->assertSee('Bitcoin (BTC)');
        $response->assertSee('Ethereum (ETH)');
        $response->assertSee('Tether (USDT)');
        
        // Should not have slot error
        $response->assertDontSee('Undefined variable $slot');
        $response->assertDontSee('Undefined variable: slot');
    }
    
    public function test_bank_deposit_page_loads()
    {
        $response = $this->get('/wallet/deposit/bank');
        
        $response->assertStatus(200);
        $response->assertSee('Bank Transfer');
    }
    
    public function test_paysera_deposit_page_loads()
    {
        $response = $this->get('/wallet/deposit/paysera');
        
        $response->assertStatus(200);
        $response->assertSee('Paysera');
    }
    
    public function test_openbanking_deposit_page_loads()
    {
        $response = $this->get('/wallet/deposit/openbanking');
        
        $response->assertStatus(200);
        $response->assertSee('Open Banking');
    }
    
    public function test_manual_deposit_page_loads()
    {
        $response = $this->get('/wallet/deposit/manual');
        
        $response->assertStatus(200);
    }
    
    public function test_deposit_page_with_account_shows_deposit_options()
    {
        // Create an account for the user
        $account = \App\Models\Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'name' => 'Test Account'
        ]);
        
        $response = $this->get('/wallet/deposit');
        
        $response->assertStatus(200);
        
        // Should not show account setup required when account exists
        $response->assertDontSee('Account Setup Required');
        
        // Should show deposit with card button
        $response->assertSee('Deposit with Card');
    }
    
    public function test_account_creation_modal_exists_on_deposit_page()
    {
        $response = $this->get('/wallet/deposit');
        
        $response->assertStatus(200);
        
        // Check modal elements exist
        $response->assertSee('id="accountModal"', false);
        $response->assertSee('Create Your Account');
        $response->assertSee('window.createAccount');
        $response->assertSee('/accounts/create');
    }
}