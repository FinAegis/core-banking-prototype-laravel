<?php

namespace Tests\Security\Penetration;

use App\Models\User;
use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SqlInjectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    /**
     * @test
     * @dataProvider sqlInjectionPayloads
     */
    public function test_account_search_is_protected_against_sql_injection($payload)
    {
        // Create test account
        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'name' => 'Test Account'
        ]);

        // Attempt SQL injection via transactions history with search parameter
        $response = $this->withToken($this->token)
            ->getJson("/api/v2/accounts/{$account->uuid}/transactions?search={$payload}");

        // Should return valid response without SQL errors
        $this->assertContains($response->status(), [200, 422]);
        
        // Should not expose SQL error details
        if ($response->status() === 500) {
            $content = $response->content();
            $this->assertStringNotContainsString('SQLSTATE', $content);
            $this->assertStringNotContainsString('SQL syntax', $content);
            $this->assertStringNotContainsString('SELECT * FROM', $content);
        }
        
        // Verify response structure if successful
        if ($response->status() === 200) {
            $data = $response->json('data');
            if (is_array($data)) {
                foreach ($data as $item) {
                    $this->assertArrayHasKey('uuid', $item);
                    $this->assertArrayNotHasKey('password', $item);
                    $this->assertArrayNotHasKey('remember_token', $item);
                }
            }
        }
    }

    /**
     * @test
     * @dataProvider sqlInjectionPayloads
     */
    public function test_transaction_filters_are_protected_against_sql_injection($payload)
    {
        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid
        ]);

        // Test various filter parameters
        $endpoints = [
            "/api/v2/accounts/{$account->uuid}/transactions?from_date={$payload}",
            "/api/v2/accounts/{$account->uuid}/transactions?to_date={$payload}",
            "/api/v2/accounts/{$account->uuid}/transactions?min_amount={$payload}",
            "/api/v2/accounts/{$account->uuid}/transactions?max_amount={$payload}",
            "/api/v2/accounts/{$account->uuid}/transactions?type={$payload}",
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->withToken($this->token)->getJson($endpoint);
            
            // Should handle malicious input gracefully
            $this->assertContains($response->status(), [200, 422]);
            
            // Check for SQL error exposure
            $content = $response->content();
            $this->assertStringNotContainsString('SQLSTATE', $content);
            $this->assertStringNotContainsString('SQL syntax', $content);
            $this->assertStringNotContainsString('Unknown column', $content);
        }
    }

    /**
     * @test
     * @dataProvider sqlInjectionPayloads
     */
    public function test_user_login_is_protected_against_sql_injection($payload)
    {
        // Attempt SQL injection in login credentials
        $response = $this->postJson('/api/v2/auth/login', [
            'email' => $payload,
            'password' => $payload
        ]);

        // Should return authentication error or not found, not SQL error
        $this->assertContains($response->status(), [401, 404, 422]);
        
        // Verify no SQL errors are exposed
        $content = $response->content();
        $this->assertStringNotContainsString('SQLSTATE', $content);
        $this->assertStringNotContainsString('SQL syntax', $content);
    }

    /**
     * @test
     * @dataProvider sqlInjectionPayloads
     */
    public function test_account_creation_is_protected_against_sql_injection($payload)
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/accounts', [
                'name' => $payload,
                'type' => $payload,
                'currency' => $payload
            ]);

        // Should validate input and reject malicious data
        $this->assertContains($response->status(), [201, 422]);
        
        // Verify no SQL errors exposed
        $content = $response->content();
        $this->assertStringNotContainsString('SQLSTATE', $content);
        $this->assertStringNotContainsString('SQL syntax', $content);
    }

    /**
     * @test
     */
    public function test_raw_queries_use_parameter_binding()
    {
        // This test verifies that any raw queries in the codebase use proper binding
        $maliciousId = "1' OR '1'='1";
        
        // Create a test account
        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid
        ]);

        // Attempt to query with malicious ID
        $response = $this->withToken($this->token)
            ->getJson("/api/v2/accounts/{$maliciousId}");

        // Should return 404, not expose all accounts
        $response->assertStatus(404);
    }

    /**
     * @test
     * @dataProvider sqlInjectionPayloads
     */
    public function test_webhook_endpoints_are_protected_against_sql_injection($payload)
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/webhooks', [
                'url' => "https://example.com/webhook?param={$payload}",
                'events' => ['account.created'],
                'description' => $payload
            ]);

        // Should validate input properly or return server error if webhook system not configured
        $this->assertContains($response->status(), [201, 404, 422, 500]);
        
        // No SQL errors should be exposed
        $content = $response->content();
        $this->assertStringNotContainsString('SQLSTATE', $content);
    }

    /**
     * @test
     */
    public function test_pagination_is_protected_against_sql_injection()
    {
        $payloads = [
            'page=1; DROP TABLE users;--',
            'per_page=10 UNION SELECT * FROM users',
            'sort=created_at; DELETE FROM accounts;',
            'order=DESC; UPDATE users SET role="admin"'
        ];

        // Create an account for testing
        $account = Account::factory()->create(['user_uuid' => $this->user->uuid]);
        
        foreach ($payloads as $payload) {
            $response = $this->withToken($this->token)
                ->getJson("/api/v2/accounts/{$account->uuid}/transactions?{$payload}");

            $this->assertContains($response->status(), [200, 422]);
            
            // Should not expose SQL errors
            if ($response->status() === 500) {
                $content = $response->content();
                $this->assertStringNotContainsString('SQLSTATE', $content);
                $this->assertStringNotContainsString('SQL syntax', $content);
            }
            
            // Verify tables still exist
            $this->assertTrue(DB::getSchemaBuilder()->hasTable('users'));
            $this->assertTrue(DB::getSchemaBuilder()->hasTable('accounts'));
        }
    }

    /**
     * @test
     */
    public function test_stored_procedures_are_protected()
    {
        // Test that procedure calls are properly escaped
        $payloads = [
            "'; CALL malicious_procedure(); --",
            "'); EXEC xp_cmdshell('whoami'); --",
            "'; EXECUTE IMMEDIATE 'DROP TABLE users'; --"
        ];

        foreach ($payloads as $payload) {
            $response = $this->withToken($this->token)
                ->postJson('/api/v2/transfers', [
                    'from_account' => $payload,
                    'to_account' => $payload,
                    'amount' => 100,
                    'currency' => 'USD'
                ]);

            // Should validate input
            $this->assertContains($response->status(), [422, 404]);
            
            // No procedure execution errors
            $content = $response->content();
            $this->assertStringNotContainsString('PROCEDURE', $content);
            $this->assertStringNotContainsString('EXECUTE', $content);
        }
    }

    /**
     * Common SQL injection payloads
     */
    public static function sqlInjectionPayloads(): array
    {
        return [
            'Basic injection' => ["' OR '1'='1"],
            'Union select' => ["' UNION SELECT * FROM users--"],
            'Dropped quote' => ["admin'--"],
            'Time-based blind' => ["' OR SLEEP(5)--"],
            'Boolean-based blind' => ["' OR 1=1--"],
            'Stacked queries' => ["'; DROP TABLE accounts;--"],
            'Comment injection' => ["' /*comment*/ OR /*comment*/ 1=1--"],
            'Hex encoding' => ["' OR 0x31=0x31--"],
            'Double quotes' => ['" OR "1"="1'],
            'Escaped quotes' => ["\\' OR \\'1\\'=\\'1"],
            'Unicode bypass' => ["' OR '1'='1' --"],
            'Null byte' => ["' OR '1'='1'%00"],
            'MySQL specific' => ["' OR '1'='1' #"],
            'PostgreSQL specific' => ["' OR '1'='1' --"],
            'MSSQL specific' => ["' OR '1'='1' --"],
            'NoSQL injection' => ['{"$ne": null}'],
            'XML injection' => ["' or count(/)>0 or '1'='1"],
            'LDAP injection' => ["*)(uid=*))(|(uid=*"],
            'XPath injection' => ["' or '1'='1' or '/'='"],
            'Second order' => ["admin'; INSERT INTO logs VALUES('hack')--"],
        ];
    }
}