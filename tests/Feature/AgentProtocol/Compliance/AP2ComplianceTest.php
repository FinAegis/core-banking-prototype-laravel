<?php

declare(strict_types=1);

namespace Tests\Feature\AgentProtocol\Compliance;

use App\Domain\AgentProtocol\Models\Agent;
use App\Domain\AgentProtocol\Services\JsonLDService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * AP2 (Agent Protocol 2.0) Compliance Test Suite.
 *
 * Tests all requirements of the AP2 specification including:
 * - JSON-LD formatting
 * - Discovery endpoints
 * - Message formats
 * - Protocol versioning
 */
class AP2ComplianceTest extends TestCase
{
    use RefreshDatabase;

    private JsonLDService $jsonLdService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonLdService = app(JsonLDService::class);
    }

    /**
     * Test that agent discovery endpoint returns valid AP2 JSON-LD.
     */
    public function test_discovery_endpoint_returns_valid_json_ld(): void
    {
        // Create test agent
        $agent = Agent::factory()->create([
            'agent_id'     => 'test-agent-001',
            'name'         => 'Test Agent',
            'status'       => 'active',
            'capabilities' => ['payment', 'escrow'],
        ]);

        // Make discovery request
        $response = $this->getJson('/api/ap2/agents/' . $agent->agent_id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                '@context',
                '@type',
                '@id',
                'name',
                'capabilities',
                'endpoints',
                'publicKey',
                'protocolVersion',
            ])
            ->assertJson([
                '@type'           => 'Agent',
                'protocolVersion' => '2.0',
            ]);

        // Validate JSON-LD format
        $data = $response->json();
        $this->assertTrue($this->jsonLdService->validate($data));
        $this->assertEquals('https://schema.org', $data['@context']);
    }

    /**
     * Test well-known discovery endpoint.
     */
    public function test_well_known_discovery_endpoint(): void
    {
        $response = $this->getJson('/.well-known/ap2-configuration');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'version',
                'endpoints',
                'capabilities',
                'authentication',
                'supported_formats',
            ])
            ->assertJson([
                'version'           => '2.0',
                'supported_formats' => ['json-ld', 'json'],
            ]);
    }

    /**
     * Test agent registration with AP2 compliant data.
     */
    public function test_agent_registration_with_ap2_compliance(): void
    {
        $agentData = [
            '@context'     => 'https://schema.org',
            '@type'        => 'Agent',
            'name'         => 'Compliant Agent',
            'description'  => 'An AP2 compliant agent',
            'capabilities' => ['payment', 'communication'],
            'endpoints'    => [
                'webhook'       => 'https://agent.example.com/webhook',
                'communication' => 'https://agent.example.com/messages',
            ],
            'publicKey' => 'ecdsa-public-key-here',
        ];

        $response = $this->postJson('/api/ap2/agents/register', $agentData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'agent_id',
                'did',
                'registration_token',
                'endpoints',
            ]);

        // Verify agent was created with correct format
        $agentId = $response->json('agent_id');
        $agent = Agent::where('agent_id', $agentId)->first();

        $this->assertNotNull($agent);
        $this->assertEquals('Compliant Agent', $agent->name);
        $this->assertContains('payment', $agent->capabilities);
    }

    /**
     * Test message format compliance.
     */
    public function test_message_format_compliance(): void
    {
        // Create two agents
        $sender = Agent::factory()->create();
        $recipient = Agent::factory()->create();

        $message = [
            '@context' => 'https://schema.org',
            '@type'    => 'Message',
            'from'     => $sender->did,
            'to'       => $recipient->did,
            'content'  => [
                '@type'    => 'PaymentRequest',
                'amount'   => 100.00,
                'currency' => 'USD',
            ],
            'timestamp' => now()->toIso8601String(),
            'signature' => 'message-signature',
        ];

        $response = $this->postJson('/api/ap2/messages', $message);

        $response->assertStatus(200)
            ->assertJson([
                'accepted'   => true,
                'message_id' => true,
            ]);

        // Validate message format
        $this->assertTrue($this->jsonLdService->validate($message));
    }

    /**
     * Test protocol version negotiation.
     */
    public function test_protocol_version_negotiation(): void
    {
        // Request with version 1.0
        $response = $this->withHeaders([
            'X-AP-Version' => '1.0',
        ])->getJson('/api/ap2/agents');

        $response->assertStatus(200)
            ->assertHeader('X-AP-Version', '2.0')
            ->assertHeader('X-AP-Min-Version', '1.0');

        // Request with unsupported version
        $response = $this->withHeaders([
            'X-AP-Version' => '0.5',
        ])->getJson('/api/ap2/agents');

        $response->assertStatus(400)
            ->assertJson([
                'error'              => 'unsupported_version',
                'supported_versions' => ['1.0', '1.1', '2.0'],
            ]);
    }

    /**
     * Test capability discovery.
     */
    public function test_capability_discovery(): void
    {
        $agent = Agent::factory()->create([
            'capabilities' => ['payment', 'escrow', 'communication'],
        ]);

        $response = $this->getJson('/api/ap2/agents/' . $agent->agent_id . '/capabilities');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '@context',
                '@type',
                'capabilities' => [
                    '*' => [
                        'name',
                        'version',
                        'endpoints',
                        'parameters',
                    ],
                ],
            ]);

        $capabilities = $response->json('capabilities');
        $this->assertCount(3, $capabilities);
        $this->assertArrayHasKey('payment', $capabilities);
    }

    /**
     * Test DID resolution.
     */
    public function test_did_resolution(): void
    {
        $agent = Agent::factory()->create([
            'did' => 'did:ap2:test-agent-123',
        ]);

        $response = $this->getJson('/api/ap2/did/' . urlencode($agent->did));

        $response->assertStatus(200)
            ->assertJsonStructure([
                '@context',
                'id',
                'publicKey',
                'authentication',
                'service',
            ])
            ->assertJson([
                'id' => $agent->did,
            ]);
    }

    /**
     * Test authentication challenge-response.
     */
    public function test_authentication_challenge_response(): void
    {
        $agent = Agent::factory()->create();

        // Request challenge
        $response = $this->postJson('/api/ap2/auth/challenge', [
            'agent_id' => $agent->agent_id,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'challenge',
                'expires_at',
            ]);

        $challenge = $response->json('challenge');

        // Respond to challenge (mock signature)
        $response = $this->postJson('/api/ap2/auth/verify', [
            'agent_id'  => $agent->agent_id,
            'challenge' => $challenge,
            'signature' => base64_encode('mock-signature'),
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'expires_at',
            ]);
    }

    /**
     * Test webhook registration and validation.
     */
    public function test_webhook_registration_and_validation(): void
    {
        $agent = Agent::factory()->create();

        $webhookData = [
            'url'    => 'https://agent.example.com/webhook',
            'events' => ['payment.received', 'message.received'],
            'secret' => 'webhook-secret-key',
        ];

        $response = $this->postJson(
            '/api/ap2/agents/' . $agent->agent_id . '/webhooks',
            $webhookData
        );

        $response->assertStatus(201)
            ->assertJsonStructure([
                'webhook_id',
                'url',
                'events',
                'status',
            ]);

        // Verify webhook validation call
        Http::fake([
            'agent.example.com/*' => Http::response(['verified' => true], 200),
        ]);

        $webhookId = $response->json('webhook_id');
        $response = $this->postJson('/api/ap2/webhooks/' . $webhookId . '/verify');

        $response->assertStatus(200)
            ->assertJson([
                'verified' => true,
            ]);
    }

    /**
     * Test rate limiting compliance.
     */
    public function test_rate_limiting_compliance(): void
    {
        $agent = Agent::factory()->create();

        // Make multiple requests
        for ($i = 0; $i < 100; $i++) {
            $response = $this->getJson('/api/ap2/agents/' . $agent->agent_id);

            if ($response->status() === 429) {
                // Rate limit hit
                $response->assertHeader('X-RateLimit-Limit')
                    ->assertHeader('X-RateLimit-Remaining')
                    ->assertHeader('X-RateLimit-Reset')
                    ->assertJson([
                        'error' => 'rate_limit_exceeded',
                    ]);
                break;
            }
        }

        // Ensure rate limiting is in place
        $this->assertTrue($response->status() === 429 || $i === 99);
    }

    /**
     * Test error response format compliance.
     */
    public function test_error_response_format_compliance(): void
    {
        // Request non-existent agent
        $response = $this->getJson('/api/ap2/agents/non-existent-agent');

        $response->assertStatus(404)
            ->assertJsonStructure([
                '@context',
                '@type',
                'error',
                'message',
                'timestamp',
                'request_id',
            ])
            ->assertJson([
                '@type' => 'Error',
                'error' => 'agent_not_found',
            ]);

        // Validate error response is valid JSON-LD
        $errorData = $response->json();
        $this->assertTrue($this->jsonLdService->validate($errorData));
    }

    /**
     * Test pagination compliance.
     */
    public function test_pagination_compliance(): void
    {
        // Create multiple agents
        Agent::factory()->count(25)->create();

        $response = $this->getJson('/api/ap2/agents?page=1&limit=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '@context',
                '@type',
                'items',
                'pagination' => [
                    'current_page',
                    'total_pages',
                    'per_page',
                    'total_items',
                    'next',
                    'previous',
                ],
            ])
            ->assertJson([
                '@type'      => 'Collection',
                'pagination' => [
                    'current_page' => 1,
                    'per_page'     => 10,
                ],
            ]);

        $items = $response->json('items');
        $this->assertCount(10, $items);
    }

    /**
     * Test content negotiation.
     */
    public function test_content_negotiation(): void
    {
        $agent = Agent::factory()->create();

        // Request JSON-LD
        $response = $this->withHeaders([
            'Accept' => 'application/ld+json',
        ])->getJson('/api/ap2/agents/' . $agent->agent_id);

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/ld+json')
            ->assertJsonStructure(['@context', '@type']);

        // Request plain JSON
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->getJson('/api/ap2/agents/' . $agent->agent_id);

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json');
    }

    /**
     * Test CORS headers.
     */
    public function test_cors_headers(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://external-agent.com',
        ])->options('/api/ap2/agents');

        $response->assertStatus(200)
            ->assertHeader('Access-Control-Allow-Origin')
            ->assertHeader('Access-Control-Allow-Methods')
            ->assertHeader('Access-Control-Allow-Headers');
    }
}
