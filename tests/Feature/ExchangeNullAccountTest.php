<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExchangeNullAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_exchange_page_handles_user_without_account()
    {
        // Create a user without an account
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        
        // Create a team for the user (required by Jetstream)
        $team = \App\Models\Team::factory()->create([
            'user_id' => $user->id,
            'personal_team' => true,
        ]);
        
        $user->current_team_id = $team->id;
        $user->save();
        
        // Create assets for exchange
        Asset::firstOrCreate(['code' => 'EUR'], [
            'name' => 'Euro',
            'type' => 'fiat',
            'is_enabled' => true,
            'is_tradeable' => true,
            'decimal_places' => 2
        ]);
        
        Asset::firstOrCreate(['code' => 'BTC'], [
            'name' => 'Bitcoin',
            'type' => 'crypto',
            'is_enabled' => true,
            'is_tradeable' => true,
            'decimal_places' => 8
        ]);
        
        // Verify user has no account
        $this->assertNull($user->account);
        
        // Visit exchange page
        $response = $this->actingAs($user)->get('/exchange');
        
        // Should load successfully without null errors
        $response->assertStatus(200);
        $response->assertDontSee('Attempt to read property');
        $response->assertDontSee('on null');
        $response->assertViewHas('userOrders');
        
        // User orders should be empty collection, not array
        $userOrders = $response->viewData('userOrders');
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $userOrders);
        $this->assertTrue($userOrders->isEmpty());
    }
    
    public function test_exchange_page_with_authenticated_user_with_account()
    {
        // Create a user with an account
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        
        // Create a team
        $team = \App\Models\Team::factory()->create([
            'user_id' => $user->id,
            'personal_team' => true,
        ]);
        
        $user->current_team_id = $team->id;
        $user->save();
        
        // Create an account for the user
        $account = \App\Models\Account::factory()->create([
            'user_uuid' => $user->uuid,
            'name' => $user->name . "'s Account",
        ]);
        
        // Create assets
        Asset::firstOrCreate(['code' => 'EUR'], [
            'name' => 'Euro',
            'type' => 'fiat',
            'is_enabled' => true,
            'is_tradeable' => true,
            'decimal_places' => 2
        ]);
        
        Asset::firstOrCreate(['code' => 'BTC'], [
            'name' => 'Bitcoin',
            'type' => 'crypto',
            'is_enabled' => true,
            'is_tradeable' => true,
            'decimal_places' => 8
        ]);
        
        // Visit exchange page
        $response = $this->actingAs($user)->get('/exchange');
        
        // Should load successfully
        $response->assertStatus(200);
        $response->assertViewHas('userOrders');
        
        // User orders should be a collection
        $userOrders = $response->viewData('userOrders');
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $userOrders);
    }
}