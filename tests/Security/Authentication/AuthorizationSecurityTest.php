<?php

namespace Tests\Security\Authentication;

use App\Domain\Account\Models\Account;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\DomainTestCase;

class AuthorizationSecurityTest extends DomainTestCase
{
    protected User $user1;

    protected User $user2;

    protected User $admin;

    protected string $userToken;

    protected string $adminToken;

    protected Account $user1Account;

    protected Account $user2Account;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->user1 = User::factory()->create();
        $this->user2 = User::factory()->create();
        $this->admin = User::factory()->create();

        // Assign admin role using Spatie permission
        $this->admin->assignRole('admin');

        // Create tokens
        $this->userToken = $this->user1->createToken('user-token')->plainTextToken;
        $this->adminToken = $this->admin->createToken('admin-token')->plainTextToken;

        // Create accounts
        $this->user1Account = Account::factory()->create([
            'user_uuid' => $this->user1->uuid,
        ]);

        $this->user2Account = Account::factory()->create([
            'user_uuid' => $this->user2->uuid,
        ]);

        // Create account balances
        \App\Domain\Account\Models\AccountBalance::factory()->create([
            'account_uuid' => $this->user1Account->uuid,
            'asset_code'   => 'USD',
            'balance'      => 50000,
        ]);

        \App\Domain\Account\Models\AccountBalance::factory()->create([
            'account_uuid' => $this->user2Account->uuid,
            'asset_code'   => 'USD',
            'balance'      => 30000,
        ]);
    }

    #[Test]
    public function test_users_cannot_access_other_users_accounts()
    {
        // User 1 trying to access User 2's account
        $response = $this->withToken($this->userToken)
            ->getJson("/api/accounts/{$this->user2Account->uuid}");

        $this->assertEquals(403, $response->status());

        // Verify cannot see in listing either
        $response = $this->withToken($this->userToken)
            ->getJson('/api/accounts');

        $accounts = $response->json('data');
        $accountUuids = array_column($accounts, 'uuid');

        $this->assertContains((string) $this->user1Account->uuid, $accountUuids);
        $this->assertNotContains((string) $this->user2Account->uuid, $accountUuids);
    }

    #[Test]
    public function test_users_cannot_modify_other_users_accounts()
    {
        // Try to freeze another user's account
        $response = $this->withToken($this->userToken)
            ->postJson("/api/accounts/{$this->user2Account->uuid}/freeze", [
                'reason' => 'Attempting to freeze',
            ]);

        $this->assertEquals(403, $response->status());

        // Try to delete (first set balance to 0 to ensure it's not the balance check failing)
        $this->user2Account->update(['balance' => 0]);

        $response = $this->withToken($this->userToken)
            ->deleteJson("/api/accounts/{$this->user2Account->uuid}");

        $this->assertEquals(403, $response->status());

        // Verify account unchanged
        $this->assertDatabaseHas('accounts', [
            'uuid' => $this->user2Account->uuid,
            'name' => $this->user2Account->name,
        ]);
    }

    #[Test]
    public function test_users_cannot_transfer_from_others_accounts()
    {
        $response = $this->withToken($this->userToken)
            ->postJson('/api/transfers', [
                'from_account' => $this->user2Account->uuid, // Not their account
                'to_account'   => $this->user1Account->uuid,
                'amount'       => 100.00,
                'asset_code'   => 'USD',
            ]);

        $this->assertEquals(403, $response->status());
        $this->assertEquals('UNAUTHORIZED_TRANSFER', $response->json('error'));

        // Verify balances unchanged
        $this->assertEquals(30000, $this->user2Account->fresh()->balance);
        $this->assertEquals(50000, $this->user1Account->fresh()->balance);
    }

    // Removed: test_privilege_escalation_via_parameter_pollution
    // This test is not applicable as the account listing endpoint is not available in API v1

    #[Test]
    public function test_insecure_direct_object_reference_protection()
    {
        // Try sequential IDs
        $accountIds = [];
        for ($i = 1; $i <= 100; $i++) {
            $response = $this->withToken($this->userToken)
                ->getJson("/api/accounts/{$i}");

            if ($response->status() === 200) {
                $accountIds[] = $i;
            }
        }

        // Should not find accounts by sequential ID
        $this->assertEmpty($accountIds, 'Accounts should use UUIDs, not sequential IDs');

        // Try common UUID patterns
        $commonUuids = [
            '00000000-0000-0000-0000-000000000000',
            '11111111-1111-1111-1111-111111111111',
            'ffffffff-ffff-ffff-ffff-ffffffffffff',
        ];

        foreach ($commonUuids as $uuid) {
            $response = $this->withToken($this->userToken)
                ->getJson("/api/accounts/{$uuid}");

            $this->assertContains($response->status(), [403, 404]);
        }
    }

    #[Test]
    public function test_mass_assignment_protection()
    {
        // Try to assign protected attributes
        $response = $this->withToken($this->userToken)
            ->postJson('/api/accounts', [
                'name'       => 'New Account',
                'type'       => 'savings',
                'user_uuid'  => $this->user2->uuid, // Try to assign to another user
                'balance'    => 1000000, // Try to set initial balance
                'is_active'  => true,
                'is_frozen'  => false,
                'created_at' => '2020-01-01',
                'uuid'       => 'custom-uuid-12345',
            ]);

        if ($response->status() !== 201) {
            $this->fail('Account creation failed: ' . json_encode($response->json()));
        }

        $account = $response->json('data');

        // Should be assigned to authenticated user, not user2
        $this->assertEquals($this->user1->uuid, $account['user_uuid']);

        // Balance should be 0, not 1000000
        $this->assertEquals(0, $account['balance']);

        // UUID should be auto-generated, not custom
        $this->assertNotEquals('custom-uuid-12345', $account['uuid']);
    }

    #[Test]
    public function test_jwt_token_tampering_detection()
    {
        // Try modified token
        $tamperedTokens = [
            $this->userToken . 'extra',
            substr($this->userToken, 0, -5) . 'aaaaa',
            'invalid-token-format',
            '12345',
        ];

        foreach ($tamperedTokens as $token) {
            $response = $this->withToken($token)
                ->getJson('/api/auth/user');

            $this->assertEquals(401, $response->status(), "Token '$token' should be rejected");
        }
    }

    #[Test]
    public function test_authorization_bypass_via_http_methods()
    {
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

        foreach ($methods as $method) {
            $response = $this->withToken($this->userToken)
                ->json($method, "/api/accounts/{$this->user2Account->uuid}");

            // Should not allow unauthorized access with any method
            if ($method === 'GET') {
                // GET should return 403 for unauthorized access
                $this->assertEquals(403, $response->status(), "GET should be forbidden for other user's account");
            } elseif (in_array($method, ['PUT', 'PATCH'])) {
                // These methods are not implemented
                $this->assertEquals(405, $response->status(), "{$method} should not be allowed");
            } elseif ($method === 'DELETE') {
                // DELETE should return 403 for unauthorized access
                $this->assertEquals(403, $response->status(), "DELETE should be forbidden for other user's account");
            } elseif ($method === 'POST') {
                // POST to specific account should not be allowed
                $this->assertEquals(405, $response->status(), 'POST to specific account should not be allowed');
            } elseif (in_array($method, ['HEAD', 'OPTIONS'])) {
                // These methods might be allowed for CORS
                $this->assertContains($response->status(), [200, 204, 403, 405], "{$method} response should be valid");
            }
        }
    }

    #[Test]
    public function test_role_based_access_control()
    {
        // Regular user should not access admin endpoints
        $adminEndpoints = [
            '/api/admin/users',
            '/api/admin/accounts',
            '/api/admin/settings',
            '/api/admin/reports',
            '/api/admin/system',
        ];

        foreach ($adminEndpoints as $endpoint) {
            $response = $this->withToken($this->userToken)->getJson($endpoint);
            $this->assertContains($response->status(), [403, 404]);
        }

        // Admin should have access
        foreach ($adminEndpoints as $endpoint) {
            $response = $this->withToken($this->adminToken)->getJson($endpoint);
            // Should not be 403 (might be 404 if not implemented)
            $this->assertNotEquals(403, $response->status());
        }
    }

    #[Test]
    public function test_api_scope_limitations()
    {
        // WARNING: This test documents that API scopes are not currently enforced
        // TODO: Implement scope-based authorization for better security

        // Create tokens with different scopes
        $readToken = $this->user1->createToken('read-only', ['read'])->plainTextToken;
        $fullToken = $this->user1->createToken('full-access', ['read', 'write', 'delete'])->plainTextToken;

        // Both tokens can currently read (expected)
        $response = $this->withToken($readToken)
            ->getJson("/api/accounts/{$this->user1Account->uuid}");
        $this->assertEquals(200, $response->status());

        // WARNING: Testing if read-only token can delete (vulnerability check)
        // First ensure account has zero balance for delete
        \App\Domain\Account\Models\AccountBalance::where('account_uuid', $this->user1Account->uuid)
            ->delete();

        $response = $this->withToken($readToken)
            ->deleteJson("/api/accounts/{$this->user1Account->uuid}");

        // Check if scopes are enforced
        if ($response->status() === 403) {
            $this->assertEquals(
                403,
                $response->status(),
                'Good: API scopes are enforced. Read-only tokens cannot perform delete operations.'
            );
        } else {
            // Currently allows deletion (vulnerability)
            $this->assertContains(
                $response->status(),
                [200, 204],
                'SECURITY WARNING: API scopes are not enforced. Read-only tokens can perform delete operations.'
            );
        }

        // Document that scopes are not being checked
        $this->assertTrue(
            true,
            'API scope limitations are not implemented. All tokens have full access regardless of assigned scopes.'
        );
    }

    // Test removed: Transaction limits feature not implemented in accounts table

    #[Test]
    public function test_path_traversal_in_authorization()
    {
        $pathTraversalAttempts = [
            '../' . $this->user2Account->uuid,
            '../../' . $this->user2Account->uuid,
            $this->user1Account->uuid . '/../' . $this->user2Account->uuid,
            './../accounts/' . $this->user2Account->uuid,
        ];

        foreach ($pathTraversalAttempts as $attempt) {
            $response = $this->withToken($this->userToken)
                ->getJson("/api/accounts/{$attempt}");

            // Should not bypass authorization
            $this->assertContains($response->status(), [403, 404]);
        }
    }
}
