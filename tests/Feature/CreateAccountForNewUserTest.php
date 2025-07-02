<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Account;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CreateAccountForNewUserTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_automatically_creates_account_when_user_registers()
    {
        // When a new user registers
        $user = User::factory()->create();
        
        // Clear any accounts that might have been created by factory
        Account::query()->delete();
        
        // Fire the registered event
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
    
    #[Test]
    public function it_handles_account_creation_failure_gracefully()
    {
        // Given we have a user with invalid uuid
        $user = User::factory()->make(['uuid' => null]);
        $user->save();
        
        // When the registered event is fired
        // Then it should not throw an exception
        try {
            event(new Registered($user));
            $this->assertTrue(true); // Event fired without exception
        } catch (\Exception $e) {
            $this->fail('Event should not throw exception: ' . $e->getMessage());
        }
        
        // And no account should be created (listener should catch the exception)
        $this->assertEquals(0, Account::count());
    }
}