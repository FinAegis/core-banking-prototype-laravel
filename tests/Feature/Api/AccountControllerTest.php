<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Account;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
    }

    public function test_can_create_account()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/accounts', [
            'user_uuid' => $this->user->uuid,
            'name' => 'Test Savings Account',
            'initial_balance' => 10000,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'uuid',
                    'user_uuid',
                    'name',
                    'balance',
                    'frozen',
                    'created_at'
                ],
                'message'
            ])
            ->assertJson([
                'data' => [
                    'user_uuid' => $this->user->uuid,
                    'name' => 'Test Savings Account',
                    'frozen' => false,
                ],
                'message' => 'Account created successfully',
            ]);

        $this->assertDatabaseHas('accounts', [
            'user_uuid' => $this->user->uuid,
            'name' => 'Test Savings Account',
        ]);
    }

    public function test_can_create_account_without_initial_balance()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/accounts', [
            'user_uuid' => $this->user->uuid,
            'name' => 'Zero Balance Account',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'uuid',
                    'user_uuid',
                    'name',
                    'balance',
                    'frozen',
                    'created_at'
                ],
                'message'
            ]);
    }

    public function test_validates_required_fields_for_account_creation()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/accounts', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_uuid', 'name']);
    }

    public function test_validates_uuid_format()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/accounts', [
            'user_uuid' => 'invalid-uuid',
            'name' => 'Test Account',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_uuid']);
    }

    public function test_can_show_account()
    {
        Sanctum::actingAs($this->user);
        
        $account = Account::factory()->forUser($this->user)->create([
            'name' => 'Display Account',
            'balance' => 5000,
        ]);

        $response = $this->getJson("/api/accounts/{$account->uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'uuid',
                    'user_uuid', 
                    'name',
                    'balance',
                    'frozen',
                    'created_at',
                    'updated_at'
                ]
            ])
            ->assertJson([
                'data' => [
                    'uuid' => $account->uuid,
                    'user_uuid' => $this->user->uuid,
                    'name' => 'Display Account',
                    'balance' => 5000,
                    'frozen' => false,
                ]
            ]);
    }

    public function test_returns_404_for_nonexistent_account()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/accounts/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }

    public function test_can_delete_account_with_zero_balance()
    {
        Sanctum::actingAs($this->user);
        
        $account = Account::factory()->forUser($this->user)->create([
            'balance' => 0,
            'frozen' => false,
        ]);

        $response = $this->deleteJson("/api/accounts/{$account->uuid}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Account deletion initiated',
            ]);
    }

    public function test_cannot_delete_account_with_positive_balance()
    {
        Sanctum::actingAs($this->user);
        
        $account = Account::factory()->forUser($this->user)->create([
            'balance' => 1000,
        ]);

        $response = $this->deleteJson("/api/accounts/{$account->uuid}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete account with positive balance',
                'error' => 'ACCOUNT_HAS_BALANCE',
            ]);
    }

    public function test_cannot_delete_frozen_account()
    {
        Sanctum::actingAs($this->user);
        
        $account = Account::factory()->forUser($this->user)->create([
            'balance' => 0,
            'frozen' => true,
        ]);

        $response = $this->deleteJson("/api/accounts/{$account->uuid}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete frozen account',
                'error' => 'ACCOUNT_FROZEN',
            ]);
    }

    public function test_can_freeze_account()
    {
        Sanctum::actingAs($this->user);
        
        $account = Account::factory()->forUser($this->user)->create([
            'frozen' => false,
        ]);

        $response = $this->postJson("/api/accounts/{$account->uuid}/freeze", [
            'reason' => 'Suspicious activity detected',
            'authorized_by' => 'admin@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Account frozen successfully',
            ]);
    }

    public function test_cannot_freeze_already_frozen_account()
    {
        Sanctum::actingAs($this->user);
        
        $account = Account::factory()->forUser($this->user)->create([
            'frozen' => true,
        ]);

        $response = $this->postJson("/api/accounts/{$account->uuid}/freeze", [
            'reason' => 'Additional freezing reason',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Account is already frozen',
                'error' => 'ACCOUNT_ALREADY_FROZEN',
            ]);
    }

    public function test_validates_freeze_reason()
    {
        Sanctum::actingAs($this->user);
        
        $account = Account::factory()->forUser($this->user)->create();

        $response = $this->postJson("/api/accounts/{$account->uuid}/freeze", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_can_unfreeze_account()
    {
        Sanctum::actingAs($this->user);
        
        $account = Account::factory()->forUser($this->user)->create([
            'frozen' => true,
        ]);

        $response = $this->postJson("/api/accounts/{$account->uuid}/unfreeze", [
            'reason' => 'Investigation completed',
            'authorized_by' => 'admin@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Account unfrozen successfully',
            ]);
    }

    public function test_cannot_unfreeze_non_frozen_account()
    {
        Sanctum::actingAs($this->user);
        
        $account = Account::factory()->forUser($this->user)->create([
            'frozen' => false,
        ]);

        $response = $this->postJson("/api/accounts/{$account->uuid}/unfreeze", [
            'reason' => 'No need to unfreeze',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Account is not frozen',
                'error' => 'ACCOUNT_NOT_FROZEN',
            ]);
    }

    public function test_validates_unfreeze_reason()
    {
        Sanctum::actingAs($this->user);
        
        $account = Account::factory()->forUser($this->user)->create([
            'frozen' => true,
        ]);

        $response = $this->postJson("/api/accounts/{$account->uuid}/unfreeze", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_requires_authentication_for_all_endpoints()
    {
        $account = Account::factory()->create();

        // Test all endpoints require authentication
        $this->postJson('/api/accounts', [])->assertStatus(401);
        $this->getJson("/api/accounts/{$account->uuid}")->assertStatus(401);
        $this->deleteJson("/api/accounts/{$account->uuid}")->assertStatus(401);
        $this->postJson("/api/accounts/{$account->uuid}/freeze", [])->assertStatus(401);
        $this->postJson("/api/accounts/{$account->uuid}/unfreeze", [])->assertStatus(401);
    }
}