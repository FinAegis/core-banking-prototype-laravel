<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Account;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAccountForNewUserTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_automatically_creates_account_when_user_registers()
    {
        // Given we have no accounts
        $this->assertEquals(0, Account::count());
        
        // When a new user registers
        $user = User::factory()->create();
        event(new Registered($user));
        
        // Give the event time to process
        sleep(1);
        
        // Then an account should be created for the user
        $this->assertEquals(1, Account::count());
        
        $account = Account::first();
        $this->assertEquals($user->uuid, $account->user_uuid);
        $this->assertEquals($user->name . "'s Account", $account->name);
        $this->assertEquals(0, $account->balance);
        
        // And the user should have the account relationship
        $this->assertTrue($user->accounts()->exists());
    }
    
    /** @test */
    public function it_handles_account_creation_failure_gracefully()
    {
        // Given we have a user with invalid uuid
        $user = User::factory()->create(['uuid' => null]);
        
        // When the registered event is fired
        // Then it should not throw an exception
        $this->assertDoesNotThrow(function () use ($user) {
            event(new Registered($user));
        });
        
        // And no account should be created
        $this->assertEquals(0, Account::count());
    }
}