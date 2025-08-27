<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\ControllerTestCase;

class AccountControllerTest extends ControllerTestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    #[Test]
    public function test_index_returns_user_accounts(): void
    {
        Sanctum::actingAs($this->user);

        // Create accounts for the authenticated user
        $account1 = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'name'      => 'Checking Account',
        ]);

        // Create AccountBalance for account1
        AccountBalance::factory()->create([
            'account_uuid' => $account1->uuid,
            'asset_code'   => 'USD',
            'balance'      => 50000,
        ]);

        $account2 = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'name'      => 'Savings Account',
        ]);

        // Create AccountBalance for account2
        AccountBalance::factory()->create([
            'account_uuid' => $account2->uuid,
            'asset_code'   => 'USD',
            'balance'      => 100000,
        ]);

        // Create an account for another user (should not be returned)
        Account::factory()->create([
            'user_uuid' => $this->otherUser->uuid,
        ]);

        $response = $this->getJson('/api/accounts');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'uuid',
                        'user_uuid',
                        'name',
                        'balance',
                        'frozen',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ])
            ->assertJsonFragment([
                'uuid' => $account1->uuid,
                'name' => 'Checking Account',
            ])
            ->assertJsonFragment([
                'uuid' => $account2->uuid,
                'name' => 'Savings Account',
            ]);
    }

    #[Test]
    public function test_index_returns_empty_array_when_no_accounts(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/accounts');

        $response->assertStatus(200)
            ->assertJson(['data' => []]);
    }

    #[Test]
    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/accounts');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_store_creates_account_with_initial_balance(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/accounts', [
            'name'            => 'New Account',
            'initial_balance' => 25000, // 250.00
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'uuid',
                    'user_uuid',
                    'name',
                    'balance',
                    'frozen',
                    'created_at',
                ],
                'message',
            ])
            ->assertJson([
                'data' => [
                    'user_uuid' => $this->user->uuid,
                    'name'      => 'New Account',
                    'frozen'    => false,
                ],
                'message' => 'Account created successfully',
            ]);

        // The initial balance is processed asynchronously via workflow
        // So we just check the account was created
        $this->assertDatabaseHas('accounts', [
            'user_uuid' => $this->user->uuid,
            'name'      => 'New Account',
        ]);
    }

    #[Test]
    public function test_store_creates_account_without_initial_balance(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/accounts', [
            'name' => 'Zero Balance Account',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.balance', 0)
            ->assertJsonPath('data.name', 'Zero Balance Account');

        $this->assertDatabaseHas('accounts', [
            'user_uuid' => $this->user->uuid,
            'name'      => 'Zero Balance Account',
        ]);
    }

    #[Test]
    public function test_store_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/accounts', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function test_store_validates_input_formats(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/accounts', [
            'name'            => str_repeat('a', 256), // Too long
            'initial_balance' => -100, // Negative
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'initial_balance']);
    }

    #[Test]
    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/accounts', [
            'name' => 'Test Account',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function test_show_returns_account_details(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'name'      => 'Test Account',
            'frozen'    => false,
        ]);

        // Create AccountBalance for the test account
        AccountBalance::factory()->create([
            'account_uuid' => $account->uuid,
            'asset_code'   => 'USD',
            'balance'      => 75000,
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
                    'updated_at',
                ],
            ])
            ->assertJson([
                'data' => [
                    'uuid'      => $account->uuid,
                    'user_uuid' => $this->user->uuid,
                    'name'      => 'Test Account',
                    'frozen'    => false,
                ],
            ]);
    }

    #[Test]
    public function test_show_returns_404_for_non_existent_account(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/accounts/non-existent-uuid');

        $response->assertStatus(404);
    }

    #[Test]
    public function test_show_requires_authentication(): void
    {
        $account = Account::factory()->create();

        $response = $this->getJson("/api/accounts/{$account->uuid}");

        $response->assertStatus(401);
    }

    #[Test]
    public function test_show_prevents_access_to_other_users_accounts(): void
    {
        Sanctum::actingAs($this->user);

        $otherAccount = Account::factory()->create([
            'user_uuid' => $this->otherUser->uuid,
        ]);

        $response = $this->getJson("/api/accounts/{$otherAccount->uuid}");

        $response->assertStatus(403);
    }

    #[Test]
    public function test_destroy_deletes_account_with_zero_balance(): void
    {
        Sanctum::actingAs($this->user, ['delete']);

        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'frozen'    => false,
        ]);

        // Debug: verify the UUIDs match
        $this->assertEquals($this->user->uuid, $account->user_uuid, 'Account should belong to test user');

        $response = $this->deleteJson("/api/accounts/{$account->uuid}");

        // Debug response if not 200
        if ($response->status() !== 200) {
            dump('Response status: ' . $response->status());
            dump('Response body: ' . $response->content());
            dump('User UUID: ' . $this->user->uuid);
            dump('Account UUID: ' . $account->uuid);
            dump('Account user_uuid: ' . $account->user_uuid);
        }

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Account deletion initiated',
            ]);
    }

    #[Test]
    public function test_destroy_prevents_deletion_with_positive_balance(): void
    {
        Sanctum::actingAs($this->user, ['delete']);

        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'frozen'    => false,
        ]);

        // Create AccountBalance with positive balance
        AccountBalance::factory()->create([
            'account_uuid' => $account->uuid,
            'asset_code'   => 'USD',
            'balance'      => 10000,
        ]);

        $response = $this->deleteJson("/api/accounts/{$account->uuid}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete account with positive balance',
                'error'   => 'ACCOUNT_HAS_BALANCE',
            ]);

        $this->assertDatabaseHas('accounts', [
            'uuid' => $account->uuid,
        ]);
    }

    #[Test]
    public function test_destroy_prevents_deletion_of_frozen_account(): void
    {
        Sanctum::actingAs($this->user, ['delete']);

        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'frozen'    => true,
        ]);

        $response = $this->deleteJson("/api/accounts/{$account->uuid}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete frozen account',
                'error'   => 'ACCOUNT_FROZEN',
            ]);

        $this->assertDatabaseHas('accounts', [
            'uuid' => $account->uuid,
        ]);
    }

    #[Test]
    public function test_destroy_returns_404_for_non_existent_account(): void
    {
        Sanctum::actingAs($this->user, ['delete']);

        $response = $this->deleteJson('/api/accounts/non-existent-uuid');

        $response->assertStatus(404);
    }

    #[Test]
    public function test_destroy_requires_authentication(): void
    {
        $account = Account::factory()->create();

        $response = $this->deleteJson("/api/accounts/{$account->uuid}");

        $response->assertStatus(401);
    }

    #[Test]
    public function test_destroy_prevents_deleting_other_users_accounts(): void
    {
        Sanctum::actingAs($this->user, ['delete']);

        $otherAccount = Account::factory()->create([
            'user_uuid' => $this->otherUser->uuid,
        ]);

        $response = $this->deleteJson("/api/accounts/{$otherAccount->uuid}");

        $response->assertStatus(403);
    }

    #[Test]
    public function test_freeze_freezes_unfrozen_account(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'frozen'    => false,
        ]);

        $response = $this->postJson("/api/accounts/{$account->uuid}/freeze", [
            'reason'        => 'Suspicious activity detected',
            'authorized_by' => 'admin@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Account frozen successfully',
            ]);
    }

    #[Test]
    public function test_freeze_prevents_freezing_already_frozen_account(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'frozen'    => true,
        ]);

        $response = $this->postJson("/api/accounts/{$account->uuid}/freeze", [
            'reason' => 'Attempt to freeze again',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Account is already frozen',
                'error'   => 'ACCOUNT_ALREADY_FROZEN',
            ]);
    }

    #[Test]
    public function test_freeze_requires_reason(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'frozen'    => false,
        ]);

        $response = $this->postJson("/api/accounts/{$account->uuid}/freeze", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    #[Test]
    public function test_freeze_validates_input_length(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create();

        $response = $this->postJson("/api/accounts/{$account->uuid}/freeze", [
            'reason'        => str_repeat('a', 256), // Too long
            'authorized_by' => str_repeat('b', 256), // Too long
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason', 'authorized_by']);
    }

    #[Test]
    public function test_freeze_returns_404_for_non_existent_account(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/accounts/non-existent-uuid/freeze', [
            'reason' => 'Test reason',
        ]);

        $response->assertStatus(404);
    }

    #[Test]
    public function test_freeze_requires_authentication(): void
    {
        $account = Account::factory()->create();

        $response = $this->postJson("/api/accounts/{$account->uuid}/freeze", [
            'reason' => 'Test reason',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function test_freeze_prevents_freezing_other_users_accounts(): void
    {
        Sanctum::actingAs($this->user);

        $otherAccount = Account::factory()->create([
            'user_uuid' => $this->otherUser->uuid,
            'frozen'    => false,
        ]);

        $response = $this->postJson("/api/accounts/{$otherAccount->uuid}/freeze", [
            'reason' => 'Test reason',
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function test_unfreeze_unfreezes_frozen_account(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'frozen'    => true,
        ]);

        $response = $this->postJson("/api/accounts/{$account->uuid}/unfreeze", [
            'reason'        => 'Investigation completed',
            'authorized_by' => 'admin@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Account unfrozen successfully',
            ]);
    }

    #[Test]
    public function test_unfreeze_prevents_unfreezing_not_frozen_account(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'frozen'    => false,
        ]);

        $response = $this->postJson("/api/accounts/{$account->uuid}/unfreeze", [
            'reason' => 'Attempt to unfreeze',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Account is not frozen',
                'error'   => 'ACCOUNT_NOT_FROZEN',
            ]);
    }

    #[Test]
    public function test_unfreeze_requires_reason(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'frozen'    => true,
        ]);

        $response = $this->postJson("/api/accounts/{$account->uuid}/unfreeze", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    #[Test]
    public function test_unfreeze_returns_404_for_non_existent_account(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/accounts/non-existent-uuid/unfreeze', [
            'reason' => 'Test reason',
        ]);

        $response->assertStatus(404);
    }

    #[Test]
    public function test_unfreeze_requires_authentication(): void
    {
        $account = Account::factory()->create(['frozen' => true]);

        $response = $this->postJson("/api/accounts/{$account->uuid}/unfreeze", [
            'reason' => 'Test reason',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function test_unfreeze_prevents_unfreezing_other_users_accounts(): void
    {
        Sanctum::actingAs($this->user);

        $otherAccount = Account::factory()->create([
            'user_uuid' => $this->otherUser->uuid,
            'frozen'    => true,
        ]);

        $response = $this->postJson("/api/accounts/{$otherAccount->uuid}/unfreeze", [
            'reason' => 'Test reason',
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function test_index_orders_accounts_by_created_at_desc(): void
    {
        Sanctum::actingAs($this->user);

        $oldAccount = Account::factory()->create([
            'user_uuid'  => $this->user->uuid,
            'name'       => 'Old Account',
            'created_at' => now()->subDays(2),
        ]);

        $newAccount = Account::factory()->create([
            'user_uuid'  => $this->user->uuid,
            'name'       => 'New Account',
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/accounts');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals($newAccount->uuid, $data[0]['uuid']);
        $this->assertEquals($oldAccount->uuid, $data[1]['uuid']);
    }
}
