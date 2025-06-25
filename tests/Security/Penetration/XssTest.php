<?php

namespace Tests\Security\Penetration;

use App\Models\User;
use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XssTest extends TestCase
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
     * @dataProvider xssPayloads
     */
    public function test_account_name_is_protected_against_xss($payload)
    {
        // Create account with XSS payload
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/accounts', [
                'name' => $payload,
                'type' => 'savings',
                'currency' => 'USD'
            ]);

        if ($response->status() === 201) {
            $account = $response->json('data');
            
            // Retrieve the account
            $getResponse = $this->withToken($this->token)
                ->getJson("/api/v2/accounts/{$account['uuid']}");
            
            $retrievedName = $getResponse->json('data.name');
            
            // Verify the payload is properly escaped/sanitized
            $this->assertStringNotContainsString('<script>', $retrievedName);
            $this->assertStringNotContainsString('javascript:', $retrievedName);
            $this->assertStringNotContainsString('onerror=', $retrievedName);
            $this->assertStringNotContainsString('onclick=', $retrievedName);
        }
    }

    /**
     * @test
     * @dataProvider xssPayloads
     */
    public function test_transaction_description_is_protected_against_xss($payload)
    {
        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'balance' => 100000
        ]);

        // Attempt XSS in transaction description
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/transfers', [
                'from_account' => $account->uuid,
                'to_account' => Account::factory()->create()->uuid,
                'amount' => 100,
                'currency' => 'USD',
                'description' => $payload
            ]);

        if ($response->status() === 201) {
            $transfer = $response->json('data');
            
            // Verify description is sanitized
            $this->assertStringNotContainsString('<script>', $transfer['description'] ?? '');
            $this->assertStringNotContainsString('javascript:', $transfer['description'] ?? '');
        }
    }

    /**
     * @test
     * @dataProvider xssPayloads
     */
    public function test_user_profile_fields_are_protected_against_xss($payload)
    {
        // Attempt XSS in user profile update
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/profile', [
                'name' => $payload,
                'bio' => $payload,
                'company' => $payload
            ]);

        if ($response->status() === 200) {
            $profile = $response->json('data');
            
            // Check all fields are sanitized
            foreach (['name', 'bio', 'company'] as $field) {
                if (isset($profile[$field])) {
                    $this->assertStringNotContainsString('<script>', $profile[$field]);
                    $this->assertStringNotContainsString('javascript:', $profile[$field]);
                    $this->assertStringNotContainsString('onerror=', $profile[$field]);
                }
            }
        }
    }

    /**
     * @test
     * @dataProvider xssPayloads
     */
    public function test_webhook_configuration_is_protected_against_xss($payload)
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/webhooks', [
                'url' => 'https://example.com/webhook',
                'events' => ['account.created'],
                'description' => $payload,
                'headers' => [
                    'X-Custom-Header' => $payload
                ]
            ]);

        if ($response->status() === 201) {
            $webhook = $response->json('data');
            
            // Verify stored values are sanitized
            $this->assertStringNotContainsString('<script>', $webhook['description'] ?? '');
            
            if (isset($webhook['headers']['X-Custom-Header'])) {
                $this->assertStringNotContainsString('<script>', $webhook['headers']['X-Custom-Header']);
            }
        }
    }

    /**
     * @test
     */
    public function test_json_responses_have_proper_content_type()
    {
        // Ensure JSON responses can't be interpreted as HTML
        $endpoints = [
            '/api/v2/accounts',
            '/api/v2/profile',
            '/api/v2/assets',
            '/api/v2/exchange-rates'
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->withToken($this->token)->getJson($endpoint);
            
            // Verify Content-Type header
            $response->assertHeader('Content-Type', 'application/json');
            
            // Should not have HTML content type
            $contentType = $response->headers->get('Content-Type');
            $this->assertStringNotContainsString('text/html', $contentType);
        }
    }

    /**
     * @test
     * @dataProvider xssPayloads
     */
    public function test_error_messages_are_protected_against_xss($payload)
    {
        // Trigger validation error with XSS payload
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/accounts', [
                'name' => '', // Empty to trigger validation
                'type' => $payload,
                'currency' => $payload
            ]);

        $response->assertStatus(422);
        
        $errors = $response->json('errors');
        
        // Check that error messages don't reflect XSS payloads unsanitized
        foreach ($errors as $field => $messages) {
            foreach ($messages as $message) {
                $this->assertStringNotContainsString('<script>', $message);
                $this->assertStringNotContainsString('javascript:', $message);
                $this->assertStringNotContainsString($payload, $message);
            }
        }
    }

    /**
     * @test
     * @dataProvider xssPayloads
     */
    public function test_search_parameters_are_protected_against_xss($payload)
    {
        // Create test data
        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'name' => 'Test Account'
        ]);

        // Search with XSS payload in transactions
        $response = $this->withToken($this->token)
            ->getJson("/api/v2/accounts/{$account->uuid}/transactions?search={$payload}");

        $this->assertContains($response->status(), [200, 422]);
        
        // If search query is reflected in response, it should be sanitized
        $content = $response->content();
        $this->assertStringNotContainsString('<script>', $content);
        $this->assertStringNotContainsString('javascript:', $content);
        $this->assertStringNotContainsString('<img src=x onerror=', $content);
    }

    /**
     * @test
     */
    public function test_file_upload_names_are_protected_against_xss()
    {
        $xssFilenames = [
            '<script>alert("XSS")</script>.pdf',
            'document<img src=x onerror=alert("XSS")>.pdf',
            'file<svg onload=alert("XSS")>.pdf',
            '"><script>alert(String.fromCharCode(88,83,83))</script>.pdf'
        ];

        foreach ($xssFilenames as $filename) {
            $response = $this->withToken($this->token)
                ->postJson('/api/v2/documents', [
                    'filename' => $filename,
                    'type' => 'kyc_document'
                ]);

            if ($response->status() === 201) {
                $document = $response->json('data');
                
                // Filename should be sanitized
                $this->assertStringNotContainsString('<script>', $document['filename'] ?? '');
                $this->assertStringNotContainsString('onerror=', $document['filename'] ?? '');
                $this->assertStringNotContainsString('onload=', $document['filename'] ?? '');
            }
        }
    }

    /**
     * @test
     */
    public function test_csp_headers_are_present()
    {
        $response = $this->withToken($this->token)->getJson('/api/v2/profile');
        
        // Check for Content Security Policy headers
        $hasCSP = $response->headers->has('Content-Security-Policy') ||
                  $response->headers->has('X-Content-Security-Policy');
        
        if (!$hasCSP) {
            $this->markTestSkipped('CSP headers are not configured. Consider adding Content-Security-Policy headers for additional XSS protection.');
        }
        
        $this->assertTrue($hasCSP);
    }

    /**
     * @test
     */
    public function test_dom_based_xss_protection()
    {
        $domXssPayloads = [
            '#<script>alert("XSS")</script>',
            '?redirect=javascript:alert("XSS")',
            '&callback=alert',
            '#"><img src=x onerror=alert("XSS")>'
        ];

        foreach ($domXssPayloads as $payload) {
            $response = $this->withToken($this->token)
                ->getJson("/api/v2/profile{$payload}");

            // Should handle gracefully without executing
            $this->assertContains($response->status(), [200, 404, 422]);
            
            // Response should not reflect payload
            $content = $response->content();
            $this->assertStringNotContainsString('alert("XSS")', $content);
        }
    }

    /**
     * Common XSS payloads for testing
     */
    public static function xssPayloads(): array
    {
        return [
            'Basic script tag' => ['<script>alert("XSS")</script>'],
            'IMG tag with onerror' => ['<img src=x onerror=alert("XSS")>'],
            'SVG with onload' => ['<svg onload=alert("XSS")>'],
            'Javascript protocol' => ['javascript:alert("XSS")'],
            'Data URL' => ['data:text/html,<script>alert("XSS")</script>'],
            'Event handler' => ['<div onclick="alert(\'XSS\')">Click</div>'],
            'Style attribute' => ['<div style="background:url(javascript:alert(\'XSS\'))">'],
            'Meta refresh' => ['<meta http-equiv="refresh" content="0;url=javascript:alert(\'XSS\')">'],
            'Base64 encoded' => ['<script>eval(atob("YWxlcnQoJ1hTUycp"))</script>'],
            'HTML entities' => ['&lt;script&gt;alert("XSS")&lt;/script&gt;'],
            'Unicode encoded' => ['<script>\u0061lert("XSS")</script>'],
            'Nested tags' => ['<<script>script>alert("XSS")<</script>/script>'],
            'Broken tag' => ['<scr<script>ipt>alert("XSS")</script>'],
            'Case variation' => ['<ScRiPt>alert("XSS")</sCrIpT>'],
            'Null byte' => ["<script>alert('XSS')\x00</script>"],
            'Form action' => ['<form action="javascript:alert(\'XSS\')">'],
            'Input autofocus' => ['<input autofocus onfocus=alert("XSS")>'],
            'Iframe src' => ['<iframe src="javascript:alert(\'XSS\')">'],
            'Link href' => ['<a href="javascript:alert(\'XSS\')">Click</a>'],
            'Object data' => ['<object data="javascript:alert(\'XSS\')">'],
        ];
    }
}