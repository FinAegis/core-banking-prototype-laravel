<?php

declare(strict_types=1);

namespace Tests\Feature\AgentProtocol;

use App\Domain\AgentProtocol\Messaging\A2AProtocolNegotiationService;
use App\Domain\AgentProtocol\Messaging\NegotiationResult;
use App\Domain\AgentProtocol\Messaging\ProtocolAgreement;
use App\Models\Agent;
use App\Models\AgentApiKey;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class AgentProtocolNegotiationApiTest extends TestCase
{
    private Agent $agent1;

    private Agent $agent2;

    protected User $user;

    private string $apiKey1;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->user = User::factory()->create(['kyc_status' => 'approved']);

        // Create agents with 32 hex chars for DID
        // Note: capability must be 'messages' to match middleware requirement
        $this->agent1 = Agent::factory()->create([
            'did'          => 'did:finaegis:agent:' . bin2hex(random_bytes(16)),
            'status'       => 'active',
            'capabilities' => ['payments', 'messages', 'escrow'],
        ]);

        $this->agent2 = Agent::factory()->create([
            'did'          => 'did:finaegis:agent:' . bin2hex(random_bytes(16)),
            'status'       => 'active',
            'capabilities' => ['payments', 'messages'],
        ]);

        // Create API key for agent1 for authenticated tests
        $this->apiKey1 = 'ak_' . Str::random(40);
        AgentApiKey::create([
            'key_id'     => Str::uuid()->toString(),
            'agent_id'   => $this->agent1->agent_id,
            'name'       => 'Test API Key',
            'key_hash'   => hash('sha256', $this->apiKey1),
            'key_prefix' => substr($this->apiKey1, 0, 8),
            'scopes'     => ['*'], // Universal scope for testing
            'is_active'  => true,
        ]);
    }

    /**
     * Helper to make authenticated requests as an agent.
     */
    private function withAgentAuth(string $apiKey): self
    {
        return $this->withHeaders([
            'Authorization' => 'AgentKey ' . $apiKey,
        ]);
    }

    // =============================================================================
    // Public endpoint tests - Protocol versions
    // =============================================================================

    public function test_list_versions_returns_supported_versions(): void
    {
        $response = $this->getJson('/api/agent-protocol/protocol/versions');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'versions',
                    'preferred',
                ],
            ]);

        $data = $response->json('data');
        $this->assertIsArray($data['versions']);
        $this->assertNotEmpty($data['versions']);
        $this->assertContains('1.0', $data['versions']);
        $this->assertContains('1.1', $data['versions']);
    }

    public function test_get_version_capabilities_returns_capabilities_for_valid_version(): void
    {
        $response = $this->getJson('/api/agent-protocol/protocol/versions/1.1/capabilities');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'version' => '1.1',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'version',
                    'capabilities',
                ],
            ]);

        $capabilities = $response->json('data.capabilities');
        $this->assertIsArray($capabilities);
        $this->assertArrayHasKey('messaging', $capabilities);
        $this->assertArrayHasKey('payments', $capabilities);
        $this->assertArrayHasKey('escrow', $capabilities);
    }

    public function test_get_version_capabilities_for_version_1_0(): void
    {
        $response = $this->getJson('/api/agent-protocol/protocol/versions/1.0/capabilities');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'version' => '1.0',
                ],
            ]);

        $capabilities = $response->json('data.capabilities');
        $this->assertTrue($capabilities['messaging']);
        $this->assertTrue($capabilities['payments']);
        $this->assertFalse($capabilities['streaming']);
    }

    public function test_get_version_capabilities_returns_404_for_unsupported_version(): void
    {
        $response = $this->getJson('/api/agent-protocol/protocol/versions/2.0/capabilities');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error'   => 'Protocol version not supported',
            ])
            ->assertJsonStructure([
                'success',
                'error',
                'data' => [
                    'requested',
                    'supported',
                ],
            ]);
    }

    // =============================================================================
    // Authenticated endpoint tests - Protocol negotiation
    // =============================================================================

    public function test_negotiate_requires_authentication(): void
    {
        $response = $this->postJson(
            "/api/agent-protocol/agents/{$this->agent1->did}/protocol/negotiate",
            ['target_did' => $this->agent2->did]
        );

        $response->assertStatus(401);
    }

    public function test_negotiate_validates_initiator_did_format(): void
    {
        $response = $this->withAgentAuth($this->apiKey1)->postJson(
            '/api/agent-protocol/agents/invalid-did/protocol/negotiate',
            ['target_did' => $this->agent2->did]
        );

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error'   => 'Invalid initiator DID format',
            ]);
    }

    public function test_negotiate_validates_target_did_format(): void
    {
        $response = $this->withAgentAuth($this->apiKey1)->postJson(
            "/api/agent-protocol/agents/{$this->agent1->did}/protocol/negotiate",
            ['target_did' => 'invalid-did']
        );

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error'   => 'Invalid target DID format',
            ]);
    }

    public function test_negotiate_validates_required_target_did(): void
    {
        $response = $this->withAgentAuth($this->apiKey1)->postJson(
            "/api/agent-protocol/agents/{$this->agent1->did}/protocol/negotiate",
            []
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['target_did']);
    }

    public function test_negotiate_with_valid_data(): void
    {
        // Mock the negotiation service to return a successful result
        $agreement = new ProtocolAgreement(
            agreementId: bin2hex(random_bytes(16)),
            version: '1.1',
            initiatorDid: $this->agent1->did,
            responderDid: $this->agent2->did,
            encryptionMethod: 'aes-256-gcm',
            signatureMethod: 'ed25519',
            capabilities: ['messaging', 'payments', 'escrow'],
            agreedAt: new DateTimeImmutable(),
            expiresAt: new DateTimeImmutable('+24 hours')
        );

        $mockResult = NegotiationResult::success($agreement);

        $mockService = Mockery::mock(A2AProtocolNegotiationService::class);
        $mockService->shouldReceive('initiateNegotiation')
            ->once()
            ->with($this->agent1->did, $this->agent2->did, null)
            ->andReturn($mockResult);

        $this->app->instance(A2AProtocolNegotiationService::class, $mockService);

        $response = $this->withAgentAuth($this->apiKey1)->postJson(
            "/api/agent-protocol/agents/{$this->agent1->did}/protocol/negotiate",
            ['target_did' => $this->agent2->did]
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'negotiation',
                ],
            ]);
    }

    public function test_negotiate_with_preferred_capabilities(): void
    {
        $preferredCapabilities = ['messaging', 'payments'];

        $agreement = new ProtocolAgreement(
            agreementId: bin2hex(random_bytes(16)),
            version: '1.1',
            initiatorDid: $this->agent1->did,
            responderDid: $this->agent2->did,
            encryptionMethod: 'aes-256-gcm',
            signatureMethod: 'ed25519',
            capabilities: $preferredCapabilities,
            agreedAt: new DateTimeImmutable(),
            expiresAt: new DateTimeImmutable('+24 hours')
        );

        $mockResult = NegotiationResult::success($agreement);

        $mockService = Mockery::mock(A2AProtocolNegotiationService::class);
        $mockService->shouldReceive('initiateNegotiation')
            ->once()
            ->with($this->agent1->did, $this->agent2->did, $preferredCapabilities)
            ->andReturn($mockResult);

        $this->app->instance(A2AProtocolNegotiationService::class, $mockService);

        $response = $this->withAgentAuth($this->apiKey1)->postJson(
            "/api/agent-protocol/agents/{$this->agent1->did}/protocol/negotiate",
            [
                'target_did'             => $this->agent2->did,
                'preferred_capabilities' => $preferredCapabilities,
            ]
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    // =============================================================================
    // Authenticated endpoint tests - Get agreement
    // =============================================================================

    public function test_get_agreement_requires_authentication(): void
    {
        $response = $this->getJson(
            "/api/agent-protocol/agents/{$this->agent1->did}/protocol/agreements/{$this->agent2->did}"
        );

        $response->assertStatus(401);
    }

    public function test_get_agreement_validates_agent_did_format(): void
    {
        $response = $this->withAgentAuth($this->apiKey1)->getJson(
            "/api/agent-protocol/agents/invalid-did/protocol/agreements/{$this->agent2->did}"
        );

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error'   => 'Invalid agent DID format',
            ]);
    }

    public function test_get_agreement_validates_other_did_format(): void
    {
        $response = $this->withAgentAuth($this->apiKey1)->getJson(
            "/api/agent-protocol/agents/{$this->agent1->did}/protocol/agreements/invalid-did"
        );

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error'   => 'Invalid other agent DID format',
            ]);
    }

    public function test_get_agreement_returns_no_agreement_when_none_exists(): void
    {
        $mockService = Mockery::mock(A2AProtocolNegotiationService::class);
        $mockService->shouldReceive('getAgreedProtocol')
            ->once()
            ->andReturn(null);
        $mockService->shouldReceive('hasValidAgreement')
            ->once()
            ->andReturn(false);

        $this->app->instance(A2AProtocolNegotiationService::class, $mockService);

        $response = $this->withAgentAuth($this->apiKey1)->getJson(
            "/api/agent-protocol/agents/{$this->agent1->did}/protocol/agreements/{$this->agent2->did}"
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'has_agreement' => false,
                    'is_valid'      => false,
                    'agreement'     => null,
                ],
            ]);
    }

    public function test_get_agreement_returns_existing_agreement(): void
    {
        $agreement = new ProtocolAgreement(
            agreementId: bin2hex(random_bytes(16)),
            version: '1.1',
            initiatorDid: $this->agent1->did,
            responderDid: $this->agent2->did,
            encryptionMethod: 'aes-256-gcm',
            signatureMethod: 'ed25519',
            capabilities: ['messaging', 'payments'],
            agreedAt: new DateTimeImmutable(),
            expiresAt: new DateTimeImmutable('+24 hours')
        );

        $mockService = Mockery::mock(A2AProtocolNegotiationService::class);
        $mockService->shouldReceive('getAgreedProtocol')
            ->once()
            ->andReturn($agreement);
        $mockService->shouldReceive('hasValidAgreement')
            ->once()
            ->andReturn(true);

        $this->app->instance(A2AProtocolNegotiationService::class, $mockService);

        $response = $this->withAgentAuth($this->apiKey1)->getJson(
            "/api/agent-protocol/agents/{$this->agent1->did}/protocol/agreements/{$this->agent2->did}"
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'has_agreement' => true,
                    'is_valid'      => true,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'agreement' => [
                        'agreementId',
                        'version',
                        'initiatorDid',
                        'responderDid',
                        'encryptionMethod',
                        'signatureMethod',
                        'capabilities',
                        'agreedAt',
                        'expiresAt',
                    ],
                    'has_agreement',
                    'is_valid',
                ],
            ]);
    }

    // =============================================================================
    // Authenticated endpoint tests - Revoke agreement
    // =============================================================================

    public function test_revoke_agreement_requires_authentication(): void
    {
        $response = $this->deleteJson(
            "/api/agent-protocol/agents/{$this->agent1->did}/protocol/agreements/{$this->agent2->did}"
        );

        $response->assertStatus(401);
    }

    public function test_revoke_agreement_validates_agent_did_format(): void
    {
        $response = $this->withAgentAuth($this->apiKey1)->deleteJson(
            "/api/agent-protocol/agents/invalid-did/protocol/agreements/{$this->agent2->did}"
        );

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error'   => 'Invalid agent DID format',
            ]);
    }

    public function test_revoke_agreement_validates_other_did_format(): void
    {
        $response = $this->withAgentAuth($this->apiKey1)->deleteJson(
            "/api/agent-protocol/agents/{$this->agent1->did}/protocol/agreements/invalid-did"
        );

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error'   => 'Invalid other agent DID format',
            ]);
    }

    public function test_revoke_agreement_succeeds(): void
    {
        $mockService = Mockery::mock(A2AProtocolNegotiationService::class);
        $mockService->shouldReceive('revokeAgreement')
            ->once()
            ->with($this->agent1->did, $this->agent2->did)
            ->andReturn(true);

        $this->app->instance(A2AProtocolNegotiationService::class, $mockService);

        $response = $this->withAgentAuth($this->apiKey1)->deleteJson(
            "/api/agent-protocol/agents/{$this->agent1->did}/protocol/agreements/{$this->agent2->did}"
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Protocol agreement revoked',
            ]);
    }

    // =============================================================================
    // Authenticated endpoint tests - Refresh agreement
    // =============================================================================

    public function test_refresh_agreement_requires_authentication(): void
    {
        $response = $this->postJson(
            "/api/agent-protocol/agents/{$this->agent1->did}/protocol/agreements/{$this->agent2->did}/refresh"
        );

        $response->assertStatus(401);
    }

    public function test_refresh_agreement_validates_agent_did_format(): void
    {
        $response = $this->withAgentAuth($this->apiKey1)->postJson(
            "/api/agent-protocol/agents/invalid-did/protocol/agreements/{$this->agent2->did}/refresh"
        );

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error'   => 'Invalid agent DID format',
            ]);
    }

    public function test_refresh_agreement_validates_other_did_format(): void
    {
        $response = $this->withAgentAuth($this->apiKey1)->postJson(
            "/api/agent-protocol/agents/{$this->agent1->did}/protocol/agreements/invalid-did/refresh"
        );

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error'   => 'Invalid other agent DID format',
            ]);
    }

    public function test_refresh_agreement_succeeds(): void
    {
        $agreement = new ProtocolAgreement(
            agreementId: bin2hex(random_bytes(16)),
            version: '1.1',
            initiatorDid: $this->agent1->did,
            responderDid: $this->agent2->did,
            encryptionMethod: 'aes-256-gcm',
            signatureMethod: 'ed25519',
            capabilities: ['messaging', 'payments'],
            agreedAt: new DateTimeImmutable(),
            expiresAt: new DateTimeImmutable('+24 hours')
        );

        $mockResult = NegotiationResult::success($agreement);

        $mockService = Mockery::mock(A2AProtocolNegotiationService::class);
        $mockService->shouldReceive('refreshAgreement')
            ->once()
            ->with($this->agent1->did, $this->agent2->did)
            ->andReturn($mockResult);

        $this->app->instance(A2AProtocolNegotiationService::class, $mockService);

        $response = $this->withAgentAuth($this->apiKey1)->postJson(
            "/api/agent-protocol/agents/{$this->agent1->did}/protocol/agreements/{$this->agent2->did}/refresh"
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'negotiation',
                ],
            ]);
    }

    // =============================================================================
    // Authenticated endpoint tests - Check agreement
    // =============================================================================

    public function test_check_agreement_requires_authentication(): void
    {
        $response = $this->getJson(
            "/api/agent-protocol/agents/{$this->agent1->did}/protocol/agreements/{$this->agent2->did}/check"
        );

        $response->assertStatus(401);
    }

    public function test_check_agreement_validates_agent_did_format(): void
    {
        $response = $this->withAgentAuth($this->apiKey1)->getJson(
            "/api/agent-protocol/agents/invalid-did/protocol/agreements/{$this->agent2->did}/check"
        );

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error'   => 'Invalid agent DID format',
            ]);
    }

    public function test_check_agreement_validates_other_did_format(): void
    {
        $response = $this->withAgentAuth($this->apiKey1)->getJson(
            "/api/agent-protocol/agents/{$this->agent1->did}/protocol/agreements/invalid-did/check"
        );

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error'   => 'Invalid other agent DID format',
            ]);
    }

    public function test_check_agreement_returns_false_when_no_agreement(): void
    {
        $mockService = Mockery::mock(A2AProtocolNegotiationService::class);
        $mockService->shouldReceive('hasValidAgreement')
            ->once()
            ->with($this->agent1->did, $this->agent2->did)
            ->andReturn(false);

        $this->app->instance(A2AProtocolNegotiationService::class, $mockService);

        $response = $this->withAgentAuth($this->apiKey1)->getJson(
            "/api/agent-protocol/agents/{$this->agent1->did}/protocol/agreements/{$this->agent2->did}/check"
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'has_valid_agreement' => false,
                ],
            ]);
    }

    public function test_check_agreement_returns_true_when_valid_agreement_exists(): void
    {
        $mockService = Mockery::mock(A2AProtocolNegotiationService::class);
        $mockService->shouldReceive('hasValidAgreement')
            ->once()
            ->with($this->agent1->did, $this->agent2->did)
            ->andReturn(true);

        $this->app->instance(A2AProtocolNegotiationService::class, $mockService);

        $response = $this->withAgentAuth($this->apiKey1)->getJson(
            "/api/agent-protocol/agents/{$this->agent1->did}/protocol/agreements/{$this->agent2->did}/check"
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'has_valid_agreement' => true,
                ],
            ]);
    }
}
