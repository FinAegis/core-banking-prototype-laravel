<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\CgoPricingRound;
use App\Models\CgoInvestment;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CgoInvestmentTest extends TestCase
{
    use RefreshDatabase;
    
    protected User $user;
    protected CgoPricingRound $activePricingRound;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->activePricingRound = CgoPricingRound::factory()->active()->create([
            'round_number' => 1,
            'share_price' => 10.00,
            'max_shares_available' => 10000,
        ]);
    }
    
    /** @test */
    public function unauthenticated_users_can_view_cgo_page()
    {
        $response = $this->get('/cgo');
        
        $response->assertStatus(200);
        $response->assertSee('Continuous Growth Offering');
        $response->assertSee('Real Investment Opportunity');
    }
    
    /** @test */
    public function authenticated_users_see_invest_now_button()
    {
        $response = $this->actingAs($this->user)->get('/cgo');
        
        $response->assertStatus(200);
        $response->assertSee('Invest Now');
        $response->assertSee(route('cgo.invest'));
    }
    
    /** @test */
    public function unauthenticated_users_see_notify_form()
    {
        $response = $this->get('/cgo');
        
        $response->assertStatus(200);
        $response->assertSee('Get Early Access');
        $response->assertSee('Notify Me');
    }
    
    /** @test */
    public function users_can_submit_notification_request()
    {
        $response = $this->post('/cgo/notify', [
            'email' => 'investor@example.com',
        ]);
        
        $response->assertRedirect();
        $response->assertSessionHas('success', 'Thank you! We\'ll notify you when the CGO launches.');
        
        $this->assertDatabaseHas('cgo_notifications', [
            'email' => 'investor@example.com',
        ]);
    }
    
    /** @test */
    public function authenticated_users_can_access_invest_page()
    {
        $response = $this->actingAs($this->user)->get('/cgo/invest');
        
        $response->assertStatus(200);
        $response->assertSee('Current Investment Round');
        $response->assertSee('Make Your Investment');
    }
    
    /** @test */
    public function invest_page_shows_current_round_info()
    {
        $response = $this->actingAs($this->user)->get('/cgo/invest');
        
        $response->assertStatus(200);
        $response->assertSee('#' . $this->activePricingRound->round_number);
        $response->assertSee('$' . number_format($this->activePricingRound->share_price, 2));
        $response->assertSee(number_format($this->activePricingRound->remaining_shares, 0));
    }
    
    /** @test */
    public function users_can_make_crypto_investment()
    {
        $response = $this->actingAs($this->user)->post('/cgo/invest', [
            'amount' => 1000,
            'payment_method' => 'crypto',
            'crypto_currency' => 'BTC',
            'terms' => 'on',
        ]);
        
        $response->assertStatus(200);
        $response->assertViewIs('cgo.crypto-payment');
        $response->assertSee('Send BTC Payment');
        
        $this->assertDatabaseHas('cgo_investments', [
            'user_id' => $this->user->id,
            'amount' => 1000,
            'payment_method' => 'crypto',
            'status' => 'pending',
            'tier' => 'silver',
        ]);
    }
    
    /** @test */
    public function users_can_make_bank_transfer_investment()
    {
        $response = $this->actingAs($this->user)->post('/cgo/invest', [
            'amount' => 500,
            'payment_method' => 'bank_transfer',
            'terms' => 'on',
        ]);
        
        $response->assertStatus(200);
        $response->assertViewIs('cgo.bank-transfer');
        $response->assertSee('Complete Bank Transfer');
        
        $this->assertDatabaseHas('cgo_investments', [
            'user_id' => $this->user->id,
            'amount' => 500,
            'payment_method' => 'bank_transfer',
            'status' => 'pending',
            'tier' => 'bronze',
        ]);
    }
    
    /** @test */
    public function investment_tier_is_calculated_correctly()
    {
        // Bronze tier
        $response = $this->actingAs($this->user)->post('/cgo/invest', [
            'amount' => 100,
            'payment_method' => 'crypto',
            'crypto_currency' => 'USDT',
            'terms' => 'on',
        ]);
        
        $investment = CgoInvestment::where('user_id', $this->user->id)->latest()->first();
        $this->assertEquals('bronze', $investment->tier);
        
        // Silver tier
        $response = $this->actingAs($this->user)->post('/cgo/invest', [
            'amount' => 1000,
            'payment_method' => 'crypto',
            'crypto_currency' => 'USDT',
            'terms' => 'on',
        ]);
        
        $investment = CgoInvestment::where('user_id', $this->user->id)->latest()->first();
        $this->assertEquals('silver', $investment->tier);
        
        // Gold tier
        $response = $this->actingAs($this->user)->post('/cgo/invest', [
            'amount' => 10000,
            'payment_method' => 'crypto',
            'crypto_currency' => 'USDT',
            'terms' => 'on',
        ]);
        
        $investment = CgoInvestment::where('user_id', $this->user->id)->latest()->first();
        $this->assertEquals('gold', $investment->tier);
    }
    
    /** @test */
    public function users_cannot_exceed_one_percent_ownership_per_round()
    {
        // Create an existing investment that brings user to 0.9% ownership
        CgoInvestment::factory()->confirmed()->create([
            'user_id' => $this->user->id,
            'round_id' => $this->activePricingRound->id,
            'amount' => 90000, // 9000 shares = 0.9% of 1M total
            'shares_purchased' => 9000,
            'ownership_percentage' => 0.9,
        ]);
        
        // Try to invest more than 0.1% (would exceed 1% limit)
        $response = $this->actingAs($this->user)->post('/cgo/invest', [
            'amount' => 20000, // Would be 2000 shares = 0.2%
            'payment_method' => 'crypto',
            'crypto_currency' => 'BTC',
            'terms' => 'on',
        ]);
        
        $response->assertRedirect();
        $response->assertSessionHasErrors(['amount' => 'This investment would exceed the 1% maximum ownership limit per round.']);
    }
    
    /** @test */
    public function investment_cannot_exceed_available_shares()
    {
        $this->activePricingRound->update([
            'max_shares_available' => 100,
            'shares_sold' => 50, // Only 50 shares remaining
        ]);
        
        $response = $this->actingAs($this->user)->post('/cgo/invest', [
            'amount' => 1000, // Would need 100 shares
            'payment_method' => 'crypto',
            'crypto_currency' => 'BTC',
            'terms' => 'on',
        ]);
        
        $response->assertRedirect();
        $response->assertSessionHasErrors(['amount' => 'Not enough shares available in this round.']);
    }
    
    /** @test */
    public function minimum_investment_amount_is_enforced()
    {
        $response = $this->actingAs($this->user)->post('/cgo/invest', [
            'amount' => 50, // Below $100 minimum
            'payment_method' => 'crypto',
            'crypto_currency' => 'BTC',
            'terms' => 'on',
        ]);
        
        $response->assertSessionHasErrors(['amount']);
    }
    
    /** @test */
    public function terms_must_be_accepted()
    {
        $response = $this->actingAs($this->user)->post('/cgo/invest', [
            'amount' => 1000,
            'payment_method' => 'crypto',
            'crypto_currency' => 'BTC',
            // 'terms' => 'on', // Not accepting terms
        ]);
        
        $response->assertSessionHasErrors(['terms']);
    }
    
    /** @test */
    public function crypto_currency_required_for_crypto_payment()
    {
        $response = $this->actingAs($this->user)->post('/cgo/invest', [
            'amount' => 1000,
            'payment_method' => 'crypto',
            // 'crypto_currency' => 'BTC', // Missing crypto currency
            'terms' => 'on',
        ]);
        
        $response->assertSessionHasErrors(['crypto_currency']);
    }
    
    /** @test */
    public function users_can_view_their_investment_history()
    {
        $investments = CgoInvestment::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'round_id' => $this->activePricingRound->id,
        ]);
        
        $response = $this->actingAs($this->user)->get('/cgo/invest');
        
        $response->assertStatus(200);
        $response->assertSee('Your Investment History');
        
        foreach ($investments as $investment) {
            $response->assertSee($investment->uuid);
            $response->assertSee(number_format($investment->amount, 2));
        }
    }
    
    /** @test */
    public function no_active_round_shows_appropriate_message()
    {
        $this->activePricingRound->update(['is_active' => false]);
        
        $response = $this->actingAs($this->user)->get('/cgo/invest');
        
        $response->assertStatus(200);
        $response->assertSee('No active investment round at the moment.');
    }
}