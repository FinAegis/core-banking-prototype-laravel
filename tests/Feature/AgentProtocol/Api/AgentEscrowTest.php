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

class AgentEscrowTest extends TestCase
{
    use RefreshDatabase;

    protected string $senderDid;

    protected string $receiverDid;

    protected string $senderAgentId;

    protected string $receiverAgentId;

    protected function setUp(): void
    {
        parent::setUp();

        // Create sender agent
        $this->senderAgentId = Str::uuid()->toString();
        $this->senderDid = 'did:example:sender789';
        $senderAggregate = AgentIdentityAggregate::register(
            agentId: $this->senderAgentId,
            did: $this->senderDid,
            name: 'Escrow Sender',
            type: 'autonomous'
        );
        $senderAggregate->createWallet(
            walletId: Str::uuid()->toString(),
            currency: 'USD',
            initialBalance: 5000.00
        );
        $senderAggregate->persist();

        Agent::create([
            'agent_id' => $this->senderAgentId,
            'did'      => $this->senderDid,
            'name'     => 'Escrow Sender',
            'type'     => 'autonomous',
            'status'   => 'active',
        ]);

        // Create receiver agent
        $this->receiverAgentId = Str::uuid()->toString();
        $this->receiverDid = 'did:example:receiver321';
        $receiverAggregate = AgentIdentityAggregate::register(
            agentId: $this->receiverAgentId,
            did: $this->receiverDid,
            name: 'Escrow Receiver',
            type: 'service'
        );
        $receiverAggregate->createWallet(
            walletId: Str::uuid()->toString(),
            currency: 'USD',
            initialBalance: 0.00
        );
        $receiverAggregate->persist();

        Agent::create([
            'agent_id' => $this->receiverAgentId,
            'did'      => $this->receiverDid,
            'name'     => 'Escrow Receiver',
            'type'     => 'service',
            'status'   => 'active',
        ]);
    }

    public function test_can_create_escrow_transaction(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/agents/escrow', [
            'sender_did'   => $this->senderDid,
            'receiver_did' => $this->receiverDid,
            'amount'       => 500.00,
            'currency'     => 'USD',
            'conditions'   => [
                'delivery_confirmation' => true,
                'quality_check'         => true,
            ],
            'expires_at' => now()->addDays(7)->toIso8601String(),
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'escrow_id',
                'status',
                'sender_did',
                'receiver_did',
                'amount',
                'currency',
                'expires_at',
                'created_at',
            ])
            ->assertJson([
                'status'       => 'created',
                'sender_did'   => $this->senderDid,
                'receiver_did' => $this->receiverDid,
                'amount'       => 500.00,
                'currency'     => 'USD',
            ]);
    }

    public function test_can_release_escrow_funds(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create escrow first
        $createResponse = $this->postJson('/api/agents/escrow', [
            'sender_did'   => $this->senderDid,
            'receiver_did' => $this->receiverDid,
            'amount'       => 250.00,
            'currency'     => 'USD',
        ]);

        $escrowId = $createResponse->json('escrow_id');

        // Release the escrow as sender
        $response = $this->postJson("/api/agents/escrow/{$escrowId}/release", [
            'agent_did' => $this->senderDid,
            'reason'    => 'Conditions met',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'escrow_id' => $escrowId,
                'status'    => 'released',
                'message'   => 'Escrow released successfully',
            ]);
    }

    public function test_can_dispute_escrow(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create escrow
        $createResponse = $this->postJson('/api/agents/escrow', [
            'sender_did'   => $this->senderDid,
            'receiver_did' => $this->receiverDid,
            'amount'       => 1000.00,
            'currency'     => 'USD',
        ]);

        $escrowId = $createResponse->json('escrow_id');

        // Raise dispute as receiver
        $response = $this->postJson("/api/agents/escrow/{$escrowId}/dispute", [
            'agent_did' => $this->receiverDid,
            'reason'    => 'Service not delivered as agreed',
            'evidence'  => [
                'description' => 'Missing features',
                'attachments' => ['file1.pdf', 'file2.png'],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'escrow_id',
                'dispute_id',
                'status',
                'message',
                'disputed_at',
            ])
            ->assertJson([
                'escrow_id' => $escrowId,
                'status'    => 'disputed',
            ]);
    }

    public function test_validates_escrow_amount(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/agents/escrow', [
            'sender_did'   => $this->senderDid,
            'receiver_did' => $this->receiverDid,
            'amount'       => 0, // Invalid zero amount
            'currency'     => 'USD',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_validates_escrow_dids(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/agents/escrow', [
            'sender_did'   => '', // Empty sender DID
            'receiver_did' => $this->receiverDid,
            'amount'       => 100.00,
            'currency'     => 'USD',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sender_did']);
    }

    public function test_handles_nonexistent_agents_in_escrow(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/agents/escrow', [
            'sender_did'   => 'did:example:nonexistent',
            'receiver_did' => $this->receiverDid,
            'amount'       => 100.00,
            'currency'     => 'USD',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Sender agent not found',
            ]);
    }

    public function test_only_authorized_agents_can_release_escrow(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create escrow
        $createResponse = $this->postJson('/api/agents/escrow', [
            'sender_did'   => $this->senderDid,
            'receiver_did' => $this->receiverDid,
            'amount'       => 300.00,
            'currency'     => 'USD',
        ]);

        $escrowId = $createResponse->json('escrow_id');

        // Try to release as receiver (should fail unless arbiter)
        $response = $this->postJson("/api/agents/escrow/{$escrowId}/release", [
            'agent_did' => $this->receiverDid,
            'reason'    => 'Trying to release',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Unauthorized to release escrow',
            ]);
    }

    public function test_only_involved_parties_can_dispute_escrow(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create third agent
        $thirdAgentId = Str::uuid()->toString();
        $thirdDid = 'did:example:third';
        $thirdAggregate = AgentIdentityAggregate::register(
            agentId: $thirdAgentId,
            did: $thirdDid,
            name: 'Third Party',
            type: 'autonomous'
        );
        $thirdAggregate->persist();

        Agent::create([
            'agent_id' => $thirdAgentId,
            'did'      => $thirdDid,
            'name'     => 'Third Party',
            'type'     => 'autonomous',
            'status'   => 'active',
        ]);

        // Create escrow between sender and receiver
        $createResponse = $this->postJson('/api/agents/escrow', [
            'sender_did'   => $this->senderDid,
            'receiver_did' => $this->receiverDid,
            'amount'       => 400.00,
            'currency'     => 'USD',
        ]);

        $escrowId = $createResponse->json('escrow_id');

        // Try to dispute as third party (should fail)
        $response = $this->postJson("/api/agents/escrow/{$escrowId}/dispute", [
            'agent_did' => $thirdDid,
            'reason'    => 'Unauthorized dispute',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Only parties involved can raise disputes',
            ]);
    }

    public function test_escrow_with_release_conditions(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/agents/escrow', [
            'sender_did'   => $this->senderDid,
            'receiver_did' => $this->receiverDid,
            'amount'       => 750.00,
            'currency'     => 'USD',
            'conditions'   => [
                'milestone_1' => 'Design completed',
                'milestone_2' => 'Development done',
                'milestone_3' => 'Testing passed',
            ],
            'release_conditions' => [
                'automatic_release'       => true,
                'release_on_confirmation' => true,
                'require_both_signatures' => false,
            ],
            'expires_at' => now()->addDays(30)->toIso8601String(),
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'created',
                'amount' => 750.00,
            ]);
    }

    public function test_requires_authentication_for_escrow_operations(): void
    {
        $response = $this->postJson('/api/agents/escrow', [
            'sender_did'   => $this->senderDid,
            'receiver_did' => $this->receiverDid,
            'amount'       => 100.00,
            'currency'     => 'USD',
        ]);

        $response->assertStatus(401);
    }
}
