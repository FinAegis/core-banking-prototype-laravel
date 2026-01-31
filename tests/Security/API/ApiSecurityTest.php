<?php

namespace Tests\Security\API;

use App\Domain\Account\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\DomainTestCase;

class ApiSecurityTest extends DomainTestCase
{
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

    #[Test]
    public function test_api_requires_authentication()
    {
        // Test endpoints that require authentication
        $protectedEndpoints = [
            ['GET', '/api/accounts'],
            ['POST', '/api/accounts'],
            ['GET', '/api/profile'],
            ['GET', '/api/transactions'],
            ['POST', '/api/transfers'],
            ['GET', '/api/user'],  // This is protected
        ];

        foreach ($protectedEndpoints as [$method, $endpoint]) {
            $response = $this->json($method, $endpoint);

            $this->assertContains($response->status(), [401, 404, 405], "Endpoint {$endpoint} should require authentication, not exist, or method not allowed");
            if ($response->status() === 401) {
                // Accept different authentication error messages
                $this->assertTrue(
                    $response->json('message') === 'Unauthenticated.' ||
                    $response->json('message') === 'Authentication required',
                    'Expected authentication error message, got: ' . $response->json('message')
                );
            }
        }

        // Test public endpoints are accessible
        $publicEndpoints = [
            ['GET', '/api/exchange-rates'],
            ['GET', '/api/status'],
        ];

        foreach ($publicEndpoints as [$method, $endpoint]) {
            $response = $this->json($method, $endpoint);

            $this->assertContains($response->status(), [200, 404], "Public endpoint {$endpoint} should be accessible or not exist");
        }
    }

    #[Test]
    public function test_api_versioning_is_enforced()
    {
        // Non-existent version endpoints should return 404
        $nonExistentVersions = [
            '/api/v0/accounts',
            '/api/v3/accounts',
            '/api/v99/accounts',
        ];

        foreach ($nonExistentVersions as $endpoint) {
            $response = $this->withToken($this->token)->getJson($endpoint);
            $this->assertEquals(404, $response->status(), "Non-existent version $endpoint should return 404");
        }

        // Supported version endpoints should work
        $supportedVersions = [
            '/api/accounts',     // Legacy endpoint
            '/api/v1/accounts',  // Version 1
            '/api/v2/accounts',  // Version 2 (current)
        ];

        foreach ($supportedVersions as $endpoint) {
            $response = $this->withToken($this->token)->getJson($endpoint);
            $this->assertNotEquals(404, $response->status(), "Supported version $endpoint should not return 404");
            $this->assertContains($response->status(), [200, 201], "Supported version $endpoint should return success");
        }
    }

    #[Test]
    public function test_api_rate_limiting_per_user()
    {
        // Enable rate limiting for this test
        config(['rate_limiting.force_in_tests' => true]);

        // Clear all cache to ensure clean state
        \Illuminate\Support\Facades\Cache::flush();

        // Test auth endpoint with strict rate limit (5 requests per minute)
        $endpoint = '/api/auth/login';
        $rateLimitConfig = \App\Http\Middleware\ApiRateLimitMiddleware::getRateLimitConfig('auth');

        // Make requests up to the limit
        for ($i = 0; $i < $rateLimitConfig['limit']; $i++) {
            $response = $this->postJson($endpoint, [
                'email'    => 'test@example.com',
                'password' => 'wrong-password',
            ]);

            // Should get 422 (validation error) or 401 (unauthorized), not 429
            $this->assertContains($response->status(), [401, 422], "Request {$i} should not be rate limited");
            $this->assertNotEquals(429, $response->status(), "Request {$i} should not hit rate limit yet");
        }

        // Next request should be rate limited
        $response = $this->postJson($endpoint, [
            'email'    => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertEquals(429, $response->status(), 'Should hit rate limit after exceeding limit');
        $this->assertJson($response->content());

        $responseData = $response->json();
        $this->assertEquals('Rate limit exceeded', $responseData['error']);
        $this->assertArrayHasKey('retry_after', $responseData);
        $this->assertArrayHasKey('limit', $responseData);
        $this->assertEquals($rateLimitConfig['limit'], $responseData['limit']);

        // Check rate limit headers
        $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
        $this->assertTrue($response->headers->has('X-RateLimit-Remaining'));
        $this->assertTrue($response->headers->has('X-RateLimit-Reset'));
        $this->assertTrue($response->headers->has('Retry-After'));

        // Test that authenticated user has separate rate limit
        \Illuminate\Support\Facades\Cache::flush();
        config(['rate_limiting.force_in_tests' => true]);

        // Test query endpoint with higher rate limit (100 requests per minute)
        $queryEndpoint = '/api/accounts';
        $queryRateLimitConfig = \App\Http\Middleware\ApiRateLimitMiddleware::getRateLimitConfig('query');

        // Make 10 requests as authenticated user - should not hit rate limit
        for ($i = 0; $i < 10; $i++) {
            $response = $this->withToken($this->token)->getJson($queryEndpoint);
            $this->assertNotEquals(429, $response->status(), "Authenticated request {$i} should not hit rate limit");
        }

        // Disable rate limiting after test
        config(['rate_limiting.force_in_tests' => false]);
    }

    #[Test]
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
                ->withHeaders(['Content-Type' => 'application/json'])
                ->call('POST', '/api/accounts', [], [], [], [], $payload);

            $this->assertContains($response->status(), [400, 401, 422], "Should handle malformed JSON: {$payload}");

            // Should not expose internal errors
            $content = $response->content();
            $this->assertStringNotContainsString('ParseError', $content);
            $this->assertStringNotContainsString('SyntaxError', $content);
        }
    }

    #[Test]
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
                ->post('/api/accounts', [
                    'name' => 'Test Account',
                    'type' => 'savings',
                ]);

            $this->assertContains($response->status(), [201, 400, 415, 422], "Content type: {$contentType}");
        }
    }

    #[Test]
    public function test_api_input_size_limits()
    {
        // Test oversized payloads - use smaller sizes to avoid memory exhaustion
        $largeString = str_repeat('a', 1024 * 100); // 100KB string instead of 1MB

        // Test individual field size limits
        $response = $this->withToken($this->token)
            ->postJson('/api/accounts', [
                'name'        => $largeString,
                'description' => $largeString,
            ]);

        $this->assertContains($response->status(), [413, 422], 'Should reject oversized fields');

        // Test metadata array with many small items instead of huge ones
        $response = $this->withToken($this->token)
            ->postJson('/api/accounts', [
                'name'     => 'Test Account',
                'metadata' => array_fill(0, 1000, 'small_value'),
            ]);

        $this->assertContains($response->status(), [201, 413, 422], 'Should handle excessive metadata items');
    }

    #[Test]
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
                ->call('POST', '/api/accounts', [], [], [], [], $payload);

            // Should reject XML or handle safely
            $this->assertContains($response->status(), [400, 401, 415, 422]);

            // Should not expose file contents
            $content = $response->content();
            $this->assertStringNotContainsString('root:', $content);
            $this->assertStringNotContainsString('/etc/passwd', $content);
        }
    }

    #[Test]
    public function test_api_handles_method_override_attempts()
    {
        // Try to override HTTP method
        $overrideHeaders = [
            'X-HTTP-Method-Override' => 'DELETE',
            'X-Method-Override'      => 'DELETE',
            '_method'                => 'DELETE',
            'X-HTTP-Method'          => 'DELETE',
        ];

        // Create account using the proper event sourcing method
        $accountUuid = Str::uuid()->toString();
        \App\Domain\Account\Aggregates\LedgerAggregate::retrieve($accountUuid)
            ->createAccount(
                hydrate(
                    class: \App\Domain\Account\DataObjects\Account::class,
                    properties: [
                        'name'      => 'Test Account',
                        'user_uuid' => $this->user->uuid,
                    ]
                )
            )
            ->persist();

        $account = Account::where('uuid', $accountUuid)->first();

        foreach ($overrideHeaders as $header => $value) {
            $response = $this->withToken($this->token)
                ->withHeaders([$header => $value])
                ->post("/api/accounts/{$account->uuid}");

            // Should not delete the account
            $this->assertDatabaseHas('accounts', ['uuid' => $account->uuid]);
        }
    }

    #[Test]
    public function test_api_pagination_limits()
    {
        // Create many accounts using event sourcing
        for ($i = 0; $i < 100; $i++) {
            $accountUuid = Str::uuid()->toString();
            \App\Domain\Account\Aggregates\LedgerAggregate::retrieve($accountUuid)
                ->createAccount(
                    hydrate(
                        class: \App\Domain\Account\DataObjects\Account::class,
                        properties: [
                            'name'      => "Test Account $i",
                            'user_uuid' => $this->user->uuid,
                        ]
                    )
                )
                ->persist();
        }

        // Try to request excessive items per page
        $response = $this->withToken($this->token)
            ->getJson('/api/accounts?per_page=10000');

        $data = $response->json('data');

        // Should enforce maximum items per page
        if ($data !== null) {
            $this->assertLessThanOrEqual(100, count($data), 'Should limit items per page');
        }

        // Test negative per_page
        $response = $this->withToken($this->token)
            ->getJson('/api/accounts?per_page=-1');

        $this->assertContains($response->status(), [200, 404, 405, 422]);
        if ($response->status() === 200) {
            $this->assertNotEmpty($response->json('data'));
        }
    }

    #[Test]
    public function test_api_error_messages_dont_leak_information()
    {
        // Test behavior depends on debug mode
        $debugMode = config('app.debug');

        // Temporarily disable debug mode for this test
        config(['app.debug' => false]);

        $probes = [
            '/api/../../etc/passwd',
            '/api/accounts/../../admin',
            '/api/accounts/123; SELECT * FROM users',
            '/api/accounts/<script>alert(1)</script>',
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

        // Restore original debug mode
        config(['app.debug' => $debugMode]);
    }

    #[Test]
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
                'Origin'        => $origin,
                'Authorization' => "Bearer {$this->token}",
            ])->options('/api/accounts');

            if ($response->headers->has('Access-Control-Allow-Origin')) {
                $allowedOrigin = $response->headers->get('Access-Control-Allow-Origin');

                // Should not allow all origins
                if ($allowedOrigin === '*') {
                    $this->fail('CORS is configured to allow all origins - this is insecure for production');
                }

                // Should not allow suspicious origins
                $this->assertNotEquals('null', $allowedOrigin);
                $this->assertNotEquals('file://', $allowedOrigin);
            }
        }
    }

    #[Test]
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
                ->postJson('/api/webhooks', [
                    'url'    => $url,
                    'events' => ['account.created'],
                ]);

            $this->assertContains($response->status(), [404, 422], "Should reject webhook URL or endpoint not found: {$url}");
        }
    }

    #[Test]
    public function test_api_transaction_idempotency()
    {
        // Create account using the proper event sourcing method
        $accountUuid = Str::uuid()->toString();
        \App\Domain\Account\Aggregates\LedgerAggregate::retrieve($accountUuid)
            ->createAccount(
                hydrate(
                    class: \App\Domain\Account\DataObjects\Account::class,
                    properties: [
                        'name'      => 'Test Account',
                        'user_uuid' => $this->user->uuid,
                    ]
                )
            )
            ->persist();

        $account = Account::where('uuid', $accountUuid)->first();

        // Add balance using event sourcing with AssetTransactionAggregate
        \App\Domain\Account\Aggregates\AssetTransactionAggregate::retrieve($account->uuid)
            ->credit('USD', 100000) // 100000 cents = $1000
            ->persist();

        // Get balance before transfer
        $account = Account::where('uuid', $accountUuid)->first();
        $balanceBeforeTransfer = $account->getBalance('USD');

        // Create a second account for the transfer
        $toAccountUuid = Str::uuid()->toString();
        \App\Domain\Account\Aggregates\LedgerAggregate::retrieve($toAccountUuid)
            ->createAccount(
                hydrate(
                    class: \App\Domain\Account\DataObjects\Account::class,
                    properties: [
                        'name'      => 'Destination Account',
                        'user_uuid' => User::factory()->create()->uuid,
                    ]
                )
            )
            ->persist();

        $idempotencyKey = 'test-key-' . uniqid();

        // First request
        $response1 = $this->withToken($this->token)
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson('/api/transfers', [
                'from_account' => $account->uuid,
                'to_account'   => $toAccountUuid,
                'amount'       => 10.00, // Amount in decimal, not cents
                'asset_code'   => 'USD',
            ]);

        // Second request with same key and parameters
        $response2 = $this->withToken($this->token)
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson('/api/transfers', [
                'from_account' => $account->uuid,
                'to_account'   => $toAccountUuid,
                'amount'       => 10.00,
                'asset_code'   => 'USD',
            ]);

        // Should return same response
        if ($response1->status() !== 201) {
            $this->fail('First transfer request failed: ' . json_encode($response1->json()));
        }
        $this->assertEquals(201, $response1->status(), 'First request should succeed');
        $this->assertEquals(201, $response2->status(), 'Second request should return same status');
        $this->assertEquals($response1->json(), $response2->json(), 'Responses should be identical');

        // Check idempotency headers
        $this->assertEquals('false', $response1->headers->get('X-Idempotency-Replayed'));
        $this->assertEquals('true', $response2->headers->get('X-Idempotency-Replayed'));

        // Third request with same key but different parameters should fail
        $response3 = $this->withToken($this->token)
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson('/api/transfers', [
                'from_account' => $account->uuid,
                'to_account'   => $toAccountUuid,
                'amount'       => 20.00, // Different amount
                'asset_code'   => 'USD',
            ]);

        $this->assertEquals(409, $response3->status(), 'Different parameters with same key should conflict');
        $this->assertEquals('Idempotency key already used', $response3->json('error'));

        // Test idempotency with a new key and same request succeeds
        $newIdempotencyKey = 'test-key-' . uniqid();
        $response4 = $this->withToken($this->token)
            ->withHeaders(['Idempotency-Key' => $newIdempotencyKey])
            ->postJson('/api/transfers', [
                'from_account' => $account->uuid,
                'to_account'   => $toAccountUuid,
                'amount'       => 10.00,
                'asset_code'   => 'USD',
            ]);

        $this->assertEquals(201, $response4->status(), 'New idempotency key should allow duplicate request');
        $this->assertNotEquals($response1->json('data.uuid'), $response4->json('data.uuid'), 'Different transfer UUIDs');
    }
}
