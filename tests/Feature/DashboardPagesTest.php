<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DashboardPagesTest extends TestCase
{
    use RefreshDatabase;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        
        // Create a team for the user
        $team = \App\Models\Team::factory()->create([
            'user_id' => $this->user->id,
            'personal_team' => true,
        ]);
        
        $this->user->current_team_id = $team->id;
        $this->user->save();
        
        // Create an account for the user
        \App\Models\Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'name' => $this->user->name . "'s Account",
        ]);
        
        // Create some basic assets for exchange
        \App\Models\Asset::firstOrCreate(['code' => 'EUR'], [
            'name' => 'Euro',
            'type' => 'fiat',
            'is_enabled' => true,
            'is_tradeable' => true,
            'decimal_places' => 2
        ]);
        
        \App\Models\Asset::firstOrCreate(['code' => 'BTC'], [
            'name' => 'Bitcoin',
            'type' => 'crypto',
            'is_enabled' => true,
            'is_tradeable' => true,
            'decimal_places' => 8
        ]);
    }
    
    /**
     * Test that dashboard loads without route errors
     */
    public function test_dashboard_loads_without_route_errors(): void
    {
        $response = $this->actingAs($this->user)->get('/dashboard');
        
        $response->assertStatus(200);
        $response->assertDontSee('Route [');
        $response->assertDontSee('not defined');
        $response->assertDontSee('Exception');
    }
    
    /**
     * Test that all navigation menu links work
     */
    public function test_navigation_menu_links_work(): void
    {
        $pages = [
            '/dashboard' => 'Dashboard',
            '/wallet' => 'Wallet overview',
            '/wallet/transactions' => 'Transaction History',
            '/exchange' => 'Exchange',
            '/lending' => 'Lending Platform',
            '/liquidity' => 'Liquidity Pools',
            '/api-keys' => 'API Keys',
        ];
        
        foreach ($pages as $url => $expectedContent) {
            $response = $this->actingAs($this->user)->get($url);
            
            // All pages should either load successfully or redirect
            $this->assertTrue(
                in_array($response->status(), [200, 302]),
                "Failed for URL: $url - got status {$response->status()}"
            );
            
            // No route errors should appear
            $response->assertDontSee('Route [');
            $response->assertDontSee('not defined');
        }
    }
    
    /**
     * Test profile and account management pages
     */
    public function test_profile_pages_work(): void
    {
        $response = $this->actingAs($this->user)->get('/user/profile');
        
        $response->assertStatus(200);
        $response->assertDontSee('Route [');
        $response->assertDontSee('not defined');
    }
    
    /**
     * Test compliance pages for authorized users
     */
    public function test_compliance_pages_for_authorized_users(): void
    {
        // Give user compliance permissions
        $this->user->assignRole('compliance_officer');
        
        $pages = [
            '/compliance/metrics',
            '/compliance/aml',
            '/audit/trail',
            '/risk/analysis',
            '/monitoring/transactions',
        ];
        
        foreach ($pages as $url) {
            $response = $this->actingAs($this->user)->get($url);
            
            // Should either load or redirect (403 if unauthorized)
            $this->assertTrue(
                in_array($response->status(), [200, 302, 403]),
                "Failed for URL: $url - got status {$response->status()}"
            );
            
            // No route errors
            $response->assertDontSee('Route [');
            $response->assertDontSee('not defined');
        }
    }
    
    /**
     * Test wallet sub-pages
     */
    public function test_wallet_subpages_work(): void
    {
        $pages = [
            '/wallet/deposit' => 'Deposit',
            '/wallet/withdraw' => 'Withdraw', 
            '/wallet/transfer' => 'Transfer',
            '/wallet/convert' => 'Convert',
            '/wallet/bank-allocation' => 'Bank Allocation',
            '/wallet/blockchain' => 'Blockchain Wallet',
        ];
        
        foreach ($pages as $url => $name) {
            $response = $this->actingAs($this->user)->get($url);
            
            $this->assertTrue(
                in_array($response->status(), [200, 302]),
                "Failed for $name at URL: $url - got status {$response->status()}"
            );
            
            // No route errors
            $response->assertDontSee('Route [');
            $response->assertDontSee('not defined');
        }
    }
    
    /**
     * Test that unauthenticated users are redirected
     */
    public function test_unauthenticated_users_are_redirected(): void
    {
        $response = $this->get('/dashboard');
        
        $response->assertRedirect('/login');
    }
    
    /**
     * Test exchange sub-pages
     */
    public function test_exchange_subpages_work(): void
    {
        $pages = [
            '/exchange/orders' => 'My Orders',
            '/exchange/trades' => 'Trade History',
            '/exchange/external' => 'External Exchanges',
        ];
        
        foreach ($pages as $url => $name) {
            $response = $this->actingAs($this->user)->get($url);
            
            $this->assertTrue(
                in_array($response->status(), [200, 302]),
                "Failed for $name at URL: $url - got status {$response->status()}"
            );
            
            // No route errors
            $response->assertDontSee('Route [');
            $response->assertDontSee('not defined');
        }
    }
    
    /**
     * Test lending sub-pages
     */
    public function test_lending_subpages_work(): void
    {
        $pages = [
            '/lending/apply' => 'Apply for Loan',
        ];
        
        foreach ($pages as $url => $name) {
            $response = $this->actingAs($this->user)->get($url);
            
            $this->assertTrue(
                in_array($response->status(), [200, 302]),
                "Failed for $name at URL: $url - got status {$response->status()}"
            );
            
            // No route errors
            $response->assertDontSee('Route [');
            $response->assertDontSee('not defined');
        }
    }
    
    /**
     * Test team management pages
     */
    public function test_team_pages_work(): void
    {
        // Create a team for the user
        $team = \App\Models\Team::factory()->create([
            'user_id' => $this->user->id,
            'personal_team' => true,
        ]);
        
        $this->user->current_team_id = $team->id;
        $this->user->save();
        
        $response = $this->actingAs($this->user)->get("/teams/{$team->id}");
        
        $response->assertStatus(200);
        $response->assertDontSee('Route [');
        $response->assertDontSee('not defined');
    }
}