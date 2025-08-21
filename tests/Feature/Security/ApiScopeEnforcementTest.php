<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Domain\Account\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\DomainTestCase;

class ApiScopeEnforcementTest extends DomainTestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
        ]);

        // Create account balance
        \App\Domain\Account\Models\AccountBalance::factory()->create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'USD',
            'balance'      => 10000,
        ]);
    }

    #[Test]
    public function test_read_only_token_cannot_create_account()
    {
        // Create token with only read scope
        $readToken = $this->user->createToken('read-only', ['read'])->plainTextToken;

        $response = $this->withToken($readToken)
            ->postJson('/api/accounts', [
                'name' => 'New Account',
                'type' => 'savings',
            ]);

        $this->assertEquals(403, $response->status());
        $this->assertEquals('INSUFFICIENT_SCOPE', $response->json('error'));
        $this->assertContains('write', $response->json('required_scopes'));
    }

    #[Test]
    public function test_read_only_token_can_read_account()
    {
        // Create token with only read scope
        $readToken = $this->user->createToken('read-only', ['read'])->plainTextToken;

        $response = $this->withToken($readToken)
            ->getJson("/api/accounts/{$this->account->uuid}");

        $this->assertEquals(200, $response->status());
        $this->assertEquals($this->account->uuid, $response->json('data.uuid'));
    }

    #[Test]
    public function test_read_only_token_cannot_delete_account()
    {
        // First ensure account has zero balance for delete
        \App\Domain\Account\Models\AccountBalance::where('account_uuid', $this->account->uuid)
            ->delete();

        // Create token with only read scope
        $readToken = $this->user->createToken('read-only', ['read'])->plainTextToken;

        $response = $this->withToken($readToken)
            ->deleteJson("/api/accounts/{$this->account->uuid}");

        $this->assertEquals(403, $response->status());
        $this->assertEquals('INSUFFICIENT_SCOPE', $response->json('error'));
        $this->assertContains('delete', $response->json('required_scopes'));

        // Verify account still exists
        $this->assertDatabaseHas('accounts', [
            'uuid' => $this->account->uuid,
        ]);
    }

    #[Test]
    public function test_write_token_cannot_delete_without_delete_scope()
    {
        // First ensure account has zero balance for delete
        \App\Domain\Account\Models\AccountBalance::where('account_uuid', $this->account->uuid)
            ->delete();

        // Create token with read and write scopes (but not delete)
        $writeToken = $this->user->createToken('read-write', ['read', 'write'])->plainTextToken;

        $response = $this->withToken($writeToken)
            ->deleteJson("/api/accounts/{$this->account->uuid}");

        $this->assertEquals(403, $response->status());
        $this->assertEquals('INSUFFICIENT_SCOPE', $response->json('error'));
        $this->assertContains('delete', $response->json('required_scopes'));
    }

    #[Test]
    public function test_full_access_token_can_perform_all_operations()
    {
        // Create token with all scopes
        $fullToken = $this->user->createToken('full-access', ['read', 'write', 'delete'])->plainTextToken;

        // Test read
        $response = $this->withToken($fullToken)
            ->getJson("/api/accounts/{$this->account->uuid}");
        $this->assertEquals(200, $response->status());

        // Test write (create)
        $response = $this->withToken($fullToken)
            ->postJson('/api/accounts', [
                'name' => 'New Account',
                'type' => 'savings',
            ]);
        $this->assertEquals(201, $response->status());
        $newAccountUuid = $response->json('data.uuid');

        // Test delete
        $response = $this->withToken($fullToken)
            ->deleteJson("/api/accounts/{$newAccountUuid}");
        $this->assertContains($response->status(), [200, 204]);
    }

    #[Test]
    public function test_admin_operations_require_admin_scope()
    {
        // Create another user's account
        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->create([
            'user_uuid' => $otherUser->uuid,
        ]);

        // Create regular user token with write scope (but not admin)
        $userToken = $this->user->createToken('user-token', ['read', 'write'])->plainTextToken;

        // Try to freeze another user's account (requires admin)
        $response = $this->withToken($userToken)
            ->postJson("/api/accounts/{$otherAccount->uuid}/freeze", [
                'reason' => 'Testing freeze',
            ]);

        $this->assertEquals(403, $response->status());

        // User with write scope can freeze their own account
        $response = $this->withToken($userToken)
            ->postJson("/api/accounts/{$this->account->uuid}/freeze", [
                'reason' => 'Self freeze',
            ]);

        $this->assertContains($response->status(), [200, 204]);
    }

    #[Test]
    public function test_admin_token_can_freeze_and_unfreeze_accounts()
    {
        // Create admin user
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Admin token should have admin scope
        $adminToken = $admin->createToken('admin-token', ['read', 'write', 'delete', 'admin'])->plainTextToken;

        // Test freeze
        $response = $this->withToken($adminToken)
            ->postJson("/api/accounts/{$this->account->uuid}/freeze", [
                'reason' => 'Admin freeze test',
            ]);
        $this->assertContains($response->status(), [200, 204]);

        // Manually set the account as frozen for unfreeze test
        // (since freeze workflow is async and may not complete immediately)
        $this->account->update(['frozen' => true]);

        // Test unfreeze
        $response = $this->withToken($adminToken)
            ->postJson("/api/accounts/{$this->account->uuid}/unfreeze", [
                'reason' => 'Admin unfreeze test',
            ]);
        $this->assertContains($response->status(), [200, 204]);
    }

    #[Test]
    public function test_transfer_operations_require_write_scope()
    {
        // Create second account for transfer
        $targetAccount = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
        ]);

        // Create token with only read scope
        $readToken = $this->user->createToken('read-only', ['read'])->plainTextToken;

        $response = $this->withToken($readToken)
            ->postJson('/api/transfers', [
                'from_account' => $this->account->uuid,
                'to_account'   => $targetAccount->uuid,
                'amount'       => 50.00,
                'asset_code'   => 'USD',
            ]);

        $this->assertEquals(403, $response->status());
        $this->assertEquals('INSUFFICIENT_SCOPE', $response->json('error'));
        $this->assertContains('write', $response->json('required_scopes'));
    }

    #[Test]
    public function test_deposit_and_withdraw_require_write_scope()
    {
        // Create token with only read scope
        $readToken = $this->user->createToken('read-only', ['read'])->plainTextToken;

        // Test deposit
        $response = $this->withToken($readToken)
            ->postJson("/api/accounts/{$this->account->uuid}/deposit", [
                'amount' => 100.00,
            ]);
        $this->assertEquals(403, $response->status());

        // Test withdraw
        $response = $this->withToken($readToken)
            ->postJson("/api/accounts/{$this->account->uuid}/withdraw", [
                'amount' => 50.00,
            ]);
        $this->assertEquals(403, $response->status());
    }

    #[Test]
    public function test_token_without_scopes_gets_default_scopes()
    {
        // When we update existing tokens, they should get default scopes
        $token = $this->user->createToken('legacy-token')->plainTextToken;

        // Should be able to read and write (default for regular users)
        $response = $this->withToken($token)
            ->getJson("/api/accounts/{$this->account->uuid}");
        $this->assertEquals(200, $response->status());

        $response = $this->withToken($token)
            ->postJson('/api/accounts', [
                'name' => 'Test Account',
                'type' => 'checking',
            ]);
        $this->assertEquals(201, $response->status());
    }
}
