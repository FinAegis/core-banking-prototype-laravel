<?php

declare(strict_types=1);

namespace Tests\Feature\AgentProtocol\Api;

use App\Domain\AgentProtocol\Aggregates\AgentIdentityAggregate;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_can_register_new_agent(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/agents/register', [
            'name'         => 'Test Agent',
            'type'         => 'autonomous',
            'organization' => 'Test Org',
            'endpoints'    => ['https://example.com/webhook'],
            'capabilities' => [
                [
                    'id'                   => 'payment_processing',
                    'endpoints'            => ['https://example.com/payments'],
                    'parameters'           => ['currency' => 'USD'],
                    'required_permissions' => ['payment.write'],
                    'supported_protocols'  => ['AP2', 'A2A'],
                ],
            ],
            'metadata' => [
                'version' => '1.0',
                'region'  => 'US',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'agent_id',
                'did',
                'name',
                'type',
                'status',
                'organization',
                'endpoints',
                'capabilities',
                'metadata',
                'created_at',
                'updated_at',
            ])
            ->assertJson([
                'name'         => 'Test Agent',
                'type'         => 'autonomous',
                'status'       => 'active',
                'organization' => 'Test Org',
            ]);

        // Verify wallet was created
        $this->assertArrayHasKey('wallet_id', $response->json('metadata'));
    }

    public function test_can_register_agent_with_custom_did(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $customDid = 'did:example:custom123';

        $response = $this->postJson('/api/agents/register', [
            'name' => 'Custom DID Agent',
            'type' => 'service',
            'did'  => $customDid,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'did'  => $customDid,
                'name' => 'Custom DID Agent',
                'type' => 'service',
            ]);
    }

    public function test_can_discover_agents(): void
    {
        // Create some agents first
        $agentId1 = Str::uuid()->toString();
        $did1 = 'did:example:agent1';
        AgentIdentityAggregate::register(
            agentId: $agentId1,
            did: $did1,
            name: 'Agent 1',
            type: 'autonomous'
        )->persist();

        Agent::create([
            'agent_id'     => $agentId1,
            'did'          => $did1,
            'name'         => 'Agent 1',
            'type'         => 'autonomous',
            'status'       => 'active',
            'capabilities' => ['payment', 'messaging'],
        ]);

        $agentId2 = Str::uuid()->toString();
        $did2 = 'did:example:agent2';
        AgentIdentityAggregate::register(
            agentId: $agentId2,
            did: $did2,
            name: 'Agent 2',
            type: 'service'
        )->persist();

        Agent::create([
            'agent_id'     => $agentId2,
            'did'          => $did2,
            'name'         => 'Agent 2',
            'type'         => 'service',
            'status'       => 'active',
            'organization' => 'Test Org',
            'capabilities' => ['data_processing'],
        ]);

        $response = $this->getJson('/api/agents/discover');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'agent_id',
                        'did',
                        'name',
                        'type',
                        'capabilities',
                    ],
                ],
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_filter_discovery_by_capability(): void
    {
        // Create agents with different capabilities
        $agentId1 = Str::uuid()->toString();
        Agent::create([
            'agent_id'     => $agentId1,
            'did'          => 'did:example:payment',
            'name'         => 'Payment Agent',
            'type'         => 'service',
            'status'       => 'active',
            'capabilities' => ['payment_processing', 'escrow'],
        ]);

        $agentId2 = Str::uuid()->toString();
        Agent::create([
            'agent_id'     => $agentId2,
            'did'          => 'did:example:messaging',
            'name'         => 'Messaging Agent',
            'type'         => 'service',
            'status'       => 'active',
            'capabilities' => ['messaging', 'notification'],
        ]);

        $response = $this->getJson('/api/agents/discover?capability=payment_processing');

        $response->assertStatus(200);
        $agents = $response->json('data');
        $this->assertCount(1, $agents);
        $this->assertEquals('Payment Agent', $agents[0]['name']);
    }

    public function test_can_filter_discovery_by_organization(): void
    {
        // Create agents from different organizations
        Agent::create([
            'agent_id'     => Str::uuid()->toString(),
            'did'          => 'did:example:org1',
            'name'         => 'Org1 Agent',
            'type'         => 'autonomous',
            'status'       => 'active',
            'organization' => 'Organization One',
        ]);

        Agent::create([
            'agent_id'     => Str::uuid()->toString(),
            'did'          => 'did:example:org2',
            'name'         => 'Org2 Agent',
            'type'         => 'autonomous',
            'status'       => 'active',
            'organization' => 'Organization Two',
        ]);

        $response = $this->getJson('/api/agents/discover?organization=Organization One');

        $response->assertStatus(200);
        $agents = $response->json('data');
        $this->assertCount(1, $agents);
        $this->assertEquals('Org1 Agent', $agents[0]['name']);
    }

    public function test_can_filter_discovery_by_type(): void
    {
        // Create agents of different types
        Agent::create([
            'agent_id' => Str::uuid()->toString(),
            'did'      => 'did:example:auto1',
            'name'     => 'Autonomous Agent',
            'type'     => 'autonomous',
            'status'   => 'active',
        ]);

        Agent::create([
            'agent_id' => Str::uuid()->toString(),
            'did'      => 'did:example:service1',
            'name'     => 'Service Agent',
            'type'     => 'service',
            'status'   => 'active',
        ]);

        $response = $this->getJson('/api/agents/discover?type=service');

        $response->assertStatus(200);
        $agents = $response->json('data');
        $this->assertCount(1, $agents);
        $this->assertEquals('Service Agent', $agents[0]['name']);
    }

    public function test_can_get_agent_details(): void
    {
        // Create an agent
        $agentId = Str::uuid()->toString();
        $did = 'did:example:details';

        AgentIdentityAggregate::register(
            agentId: $agentId,
            did: $did,
            name: 'Detailed Agent',
            type: 'gateway'
        )->persist();

        Agent::create([
            'agent_id'     => $agentId,
            'did'          => $did,
            'name'         => 'Detailed Agent',
            'type'         => 'gateway',
            'status'       => 'active',
            'organization' => 'Test Corp',
            'endpoints'    => ['https://api.example.com'],
            'capabilities' => ['routing', 'translation'],
            'metadata'     => ['version' => '2.0'],
        ]);

        $response = $this->getJson("/api/agents/{$did}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'agent_id',
                'did',
                'name',
                'type',
                'status',
                'organization',
                'endpoints',
                'capabilities',
                'metadata',
            ])
            ->assertJson([
                'did'          => $did,
                'name'         => 'Detailed Agent',
                'type'         => 'gateway',
                'status'       => 'active',
                'organization' => 'Test Corp',
            ]);
    }

    public function test_returns_404_for_nonexistent_agent(): void
    {
        $response = $this->getJson('/api/agents/did:example:nonexistent');

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Agent not found',
                'did'   => 'did:example:nonexistent',
            ]);
    }

    public function test_can_update_agent_capabilities(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create an agent first
        $agentId = Str::uuid()->toString();
        $did = 'did:example:updatable';

        $aggregate = AgentIdentityAggregate::register(
            agentId: $agentId,
            did: $did,
            name: 'Updatable Agent',
            type: 'autonomous'
        );
        $aggregate->advertiseCapability(
            capabilityId: 'initial_capability',
            endpoints: ['https://initial.example.com']
        );
        $aggregate->persist();

        Agent::create([
            'agent_id'     => $agentId,
            'did'          => $did,
            'name'         => 'Updatable Agent',
            'type'         => 'autonomous',
            'status'       => 'active',
            'capabilities' => ['initial_capability'],
        ]);

        // Update capabilities
        $response = $this->putJson("/api/agents/{$did}/capabilities", [
            'action'       => 'add',
            'capabilities' => [
                [
                    'id'                   => 'new_capability',
                    'endpoints'            => ['https://new.example.com'],
                    'parameters'           => ['mode' => 'advanced'],
                    'required_permissions' => ['admin'],
                    'supported_protocols'  => ['AP2'],
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'agent_id',
                'did',
                'name',
                'capabilities',
            ]);

        $capabilities = $response->json('capabilities');
        $this->assertContains('initial_capability', $capabilities);
        $this->assertContains('new_capability', $capabilities);
    }

    public function test_can_update_existing_capabilities(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $agentId = Str::uuid()->toString();
        $did = 'did:example:update';

        AgentIdentityAggregate::register(
            agentId: $agentId,
            did: $did,
            name: 'Update Test Agent',
            type: 'service'
        )->persist();

        Agent::create([
            'agent_id'     => $agentId,
            'did'          => $did,
            'name'         => 'Update Test Agent',
            'type'         => 'service',
            'status'       => 'active',
            'capabilities' => ['old_capability'],
        ]);

        $response = $this->putJson("/api/agents/{$did}/capabilities", [
            'action'       => 'update',
            'capabilities' => [
                [
                    'id'        => 'updated_capability',
                    'endpoints' => ['https://updated.example.com'],
                ],
            ],
        ]);

        $response->assertStatus(200);
        $capabilities = $response->json('capabilities');
        $this->assertContains('updated_capability', $capabilities);
        $this->assertNotContains('old_capability', $capabilities);
    }

    public function test_requires_authentication_for_registration(): void
    {
        $response = $this->postJson('/api/agents/register', [
            'name' => 'Test Agent',
            'type' => 'autonomous',
        ]);

        $response->assertStatus(401);
    }

    public function test_requires_authentication_for_capability_update(): void
    {
        $response = $this->putJson('/api/agents/did:example:test/capabilities', [
            'action'       => 'add',
            'capabilities' => [],
        ]);

        $response->assertStatus(401);
    }

    public function test_validates_registration_required_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/agents/register', [
            // Missing required 'name' field
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_validates_agent_type(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/agents/register', [
            'name' => 'Test Agent',
            'type' => 'invalid_type', // Invalid type
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_validates_capability_structure(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/agents/register', [
            'name'         => 'Test Agent',
            'capabilities' => [
                [
                    // Missing required 'id' field in capability
                    'endpoints' => ['https://example.com'],
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['capabilities.0.id']);
    }

    public function test_only_active_agents_shown_in_discovery(): void
    {
        // Create active and inactive agents
        Agent::create([
            'agent_id' => Str::uuid()->toString(),
            'did'      => 'did:example:active',
            'name'     => 'Active Agent',
            'type'     => 'autonomous',
            'status'   => 'active',
        ]);

        Agent::create([
            'agent_id' => Str::uuid()->toString(),
            'did'      => 'did:example:inactive',
            'name'     => 'Inactive Agent',
            'type'     => 'autonomous',
            'status'   => 'inactive',
        ]);

        $response = $this->getJson('/api/agents/discover');

        $response->assertStatus(200);
        $agents = $response->json('data');
        $this->assertCount(1, $agents);
        $this->assertEquals('Active Agent', $agents[0]['name']);
    }

    public function test_agent_registration_with_default_currency(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/agents/register', [
            'name'             => 'Currency Test Agent',
            'type'             => 'autonomous',
            'default_currency' => 'EUR',
        ]);

        $response->assertStatus(201);
        // The wallet should be created with EUR currency
        $this->assertArrayHasKey('wallet_id', $response->json('metadata'));
    }
}
