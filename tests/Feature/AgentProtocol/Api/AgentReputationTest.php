<?php

declare(strict_types=1);

namespace Tests\Feature\AgentProtocol\Api;

use App\Domain\AgentProtocol\Aggregates\AgentIdentityAggregate;
use App\Domain\AgentProtocol\Aggregates\ReputationAggregate;
use App\Domain\AgentProtocol\Models\Agent;
use App\Domain\AgentProtocol\Models\AgentReputation;
use App\Domain\AgentProtocol\ValueObjects\ReputationScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentReputationTest extends TestCase
{
    use RefreshDatabase;

    protected string $targetDid;

    protected string $submitterDid;

    protected string $targetAgentId;

    protected string $submitterAgentId;

    protected function setUp(): void
    {
        parent::setUp();

        // Create target agent (the one receiving feedback)
        $this->targetAgentId = Str::uuid()->toString();
        $this->targetDid = 'did:example:target-agent';
        $targetAggregate = AgentIdentityAggregate::register(
            agentId: $this->targetAgentId,
            did: $this->targetDid,
            name: 'Target Agent',
            type: 'service'
        );
        $targetAggregate->persist();

        Agent::create([
            'agent_id' => $this->targetAgentId,
            'did'      => $this->targetDid,
            'name'     => 'Target Agent',
            'type'     => 'service',
            'status'   => 'active',
        ]);

        // Create submitter agent (the one giving feedback)
        $this->submitterAgentId = Str::uuid()->toString();
        $this->submitterDid = 'did:example:submitter-agent';
        $submitterAggregate = AgentIdentityAggregate::register(
            agentId: $this->submitterAgentId,
            did: $this->submitterDid,
            name: 'Submitter Agent',
            type: 'autonomous'
        );
        $submitterAggregate->persist();

        Agent::create([
            'agent_id' => $this->submitterAgentId,
            'did'      => $this->submitterDid,
            'name'     => 'Submitter Agent',
            'type'     => 'autonomous',
            'status'   => 'active',
        ]);
    }

    public function test_can_get_agent_reputation(): void
    {
        // Create reputation record
        $reputationId = Str::uuid()->toString();
        $reputationAggregate = ReputationAggregate::initializeReputation(
            reputationId: $reputationId,
            agentId: $this->targetAgentId,
            initialScore: new ReputationScore(75.0, 'high')
        );
        $reputationAggregate->recordTransaction(
            transactionId: Str::uuid()->toString(),
            outcome: 'success',
            value: 100.0
        );
        $reputationAggregate->persist();

        AgentReputation::create([
            'reputation_id'           => $reputationId,
            'agent_id'                => $this->targetAgentId,
            'score'                   => 75.0,
            'trust_level'             => 'trusted',
            'total_transactions'      => 1,
            'successful_transactions' => 1,
            'failed_transactions'     => 0,
            'disputed_transactions'   => 0,
        ]);

        $response = $this->getJson("/api/agents/{$this->targetDid}/reputation");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'agent_did',
                'score',
                'trust_level',
                'total_transactions',
                'successful_transactions',
                'failed_transactions',
                'disputed_transactions',
                'success_rate',
                'last_updated',
            ])
            ->assertJson([
                'agent_did'               => $this->targetDid,
                'trust_level'             => 'trusted',
                'total_transactions'      => 1,
                'successful_transactions' => 1,
            ]);
    }

    public function test_returns_default_reputation_for_new_agent(): void
    {
        // Create a new agent without reputation
        $newAgentId = Str::uuid()->toString();
        $newDid = 'did:example:new-agent';
        $newAggregate = AgentIdentityAggregate::register(
            agentId: $newAgentId,
            did: $newDid,
            name: 'New Agent',
            type: 'autonomous'
        );
        $newAggregate->persist();

        Agent::create([
            'agent_id' => $newAgentId,
            'did'      => $newDid,
            'name'     => 'New Agent',
            'type'     => 'autonomous',
            'status'   => 'active',
        ]);

        $response = $this->getJson("/api/agents/{$newDid}/reputation");

        $response->assertStatus(200)
            ->assertJson([
                'agent_did'               => $newDid,
                'score'                   => 50.0,
                'trust_level'             => 'neutral',
                'total_transactions'      => 0,
                'successful_transactions' => 0,
                'failed_transactions'     => 0,
                'disputed_transactions'   => 0,
                'success_rate'            => 0,
                'last_updated'            => null,
            ]);
    }

    public function test_can_submit_transaction_feedback(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create reputation for target agent
        $reputationId = Str::uuid()->toString();
        ReputationAggregate::initializeReputation(
            reputationId: $reputationId,
            agentId: $this->targetAgentId,
            initialScore: new ReputationScore(50.0, 'neutral')
        )->persist();

        AgentReputation::create([
            'reputation_id'           => $reputationId,
            'agent_id'                => $this->targetAgentId,
            'score'                   => 50.0,
            'trust_level'             => 'neutral',
            'total_transactions'      => 0,
            'successful_transactions' => 0,
            'failed_transactions'     => 0,
            'disputed_transactions'   => 0,
        ]);

        $response = $this->postJson("/api/agents/{$this->targetDid}/reputation/feedback", [
            'submitter_did'     => $this->submitterDid,
            'feedback_type'     => 'transaction',
            'outcome'           => 'success',
            'transaction_value' => 250.00,
            'rating'            => 5,
            'comment'           => 'Excellent service',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'agent_did',
                'score',
                'trust_level',
                'total_transactions',
                'success_rate',
                'message',
            ])
            ->assertJson([
                'agent_did' => $this->targetDid,
                'message'   => 'Feedback submitted successfully',
            ]);
    }

    public function test_can_submit_dispute_feedback(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create reputation for target agent
        $reputationId = Str::uuid()->toString();
        ReputationAggregate::initializeReputation(
            reputationId: $reputationId,
            agentId: $this->targetAgentId,
            initialScore: new ReputationScore(75.0, 'high')
        )->persist();

        AgentReputation::create([
            'reputation_id'           => $reputationId,
            'agent_id'                => $this->targetAgentId,
            'score'                   => 75.0,
            'trust_level'             => 'trusted',
            'total_transactions'      => 10,
            'successful_transactions' => 8,
            'failed_transactions'     => 2,
            'disputed_transactions'   => 0,
        ]);

        $response = $this->postJson("/api/agents/{$this->targetDid}/reputation/feedback", [
            'submitter_did' => $this->submitterDid,
            'feedback_type' => 'dispute',
            'severity'      => 'high',
            'reason'        => 'Failed to deliver promised service',
            'evidence'      => [
                'transaction_id' => 'txn-123',
                'description'    => 'Service not delivered',
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'agent_did',
                'score',
                'trust_level',
                'total_transactions',
                'success_rate',
                'message',
            ]);
    }

    public function test_can_submit_endorsement_feedback(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create reputation for target agent
        $reputationId = Str::uuid()->toString();
        ReputationAggregate::initializeReputation(
            reputationId: $reputationId,
            agentId: $this->targetAgentId,
            initialScore: new ReputationScore(60.0, 'high')
        )->persist();

        AgentReputation::create([
            'reputation_id'           => $reputationId,
            'agent_id'                => $this->targetAgentId,
            'score'                   => 60.0,
            'trust_level'             => 'neutral',
            'total_transactions'      => 5,
            'successful_transactions' => 5,
            'failed_transactions'     => 0,
            'disputed_transactions'   => 0,
        ]);

        $response = $this->postJson("/api/agents/{$this->targetDid}/reputation/feedback", [
            'submitter_did' => $this->submitterDid,
            'feedback_type' => 'endorsement',
            'reason'        => 'Consistent high-quality service',
            'boost_amount'  => 10.0,
            'comment'       => 'Highly recommended agent',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'agent_did' => $this->targetDid,
                'message'   => 'Feedback submitted successfully',
            ]);
    }

    public function test_can_submit_general_rating_feedback(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create reputation for target agent
        $reputationId = Str::uuid()->toString();
        ReputationAggregate::initializeReputation(
            reputationId: $reputationId,
            agentId: $this->targetAgentId,
            initialScore: new ReputationScore(50.0, 'neutral')
        )->persist();

        AgentReputation::create([
            'reputation_id'           => $reputationId,
            'agent_id'                => $this->targetAgentId,
            'score'                   => 50.0,
            'trust_level'             => 'neutral',
            'total_transactions'      => 0,
            'successful_transactions' => 0,
            'failed_transactions'     => 0,
            'disputed_transactions'   => 0,
        ]);

        $response = $this->postJson("/api/agents/{$this->targetDid}/reputation/feedback", [
            'submitter_did' => $this->submitterDid,
            'feedback_type' => 'general',
            'rating'        => 4,
            'comment'       => 'Good agent, reliable service',
        ]);

        $response->assertStatus(200);
    }

    public function test_validates_feedback_rating(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/agents/{$this->targetDid}/reputation/feedback", [
            'submitter_did' => $this->submitterDid,
            'feedback_type' => 'general',
            'rating'        => 10, // Invalid rating (should be 1-5)
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_validates_feedback_type(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/agents/{$this->targetDid}/reputation/feedback", [
            'submitter_did' => $this->submitterDid,
            'feedback_type' => 'invalid_type',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['feedback_type']);
    }

    public function test_handles_nonexistent_target_agent(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/agents/did:example:nonexistent/reputation/feedback', [
            'submitter_did' => $this->submitterDid,
            'feedback_type' => 'transaction',
            'outcome'       => 'success',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Target agent not found',
            ]);
    }

    public function test_handles_nonexistent_submitter_agent(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/agents/{$this->targetDid}/reputation/feedback", [
            'submitter_did' => 'did:example:nonexistent',
            'feedback_type' => 'transaction',
            'outcome'       => 'success',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Submitter agent not found',
            ]);
    }

    public function test_creates_reputation_if_not_exists(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Ensure no reputation exists for target agent
        $this->assertNull(AgentReputation::where('agent_id', $this->targetAgentId)->first());

        $response = $this->postJson("/api/agents/{$this->targetDid}/reputation/feedback", [
            'submitter_did'     => $this->submitterDid,
            'feedback_type'     => 'transaction',
            'outcome'           => 'success',
            'transaction_value' => 100.00,
        ]);

        $response->assertStatus(200);

        // Verify reputation was created
        $reputation = AgentReputation::where('agent_id', $this->targetAgentId)->first();
        $this->assertInstanceOf(AgentReputation::class, $reputation);
    }

    public function test_requires_authentication_for_feedback(): void
    {
        $response = $this->postJson("/api/agents/{$this->targetDid}/reputation/feedback", [
            'submitter_did' => $this->submitterDid,
            'feedback_type' => 'transaction',
        ]);

        $response->assertStatus(401);
    }

    public function test_reputation_score_calculation(): void
    {
        // Create agent with specific reputation
        $reputationId = Str::uuid()->toString();
        $reputationAggregate = ReputationAggregate::initializeReputation(
            reputationId: $reputationId,
            agentId: $this->targetAgentId,
            initialScore: new ReputationScore(50.0, 'neutral')
        );

        // Record multiple transactions to test calculation
        $reputationAggregate->recordTransaction(Str::uuid()->toString(), 'success', 100.0);
        $reputationAggregate->recordTransaction(Str::uuid()->toString(), 'success', 200.0);
        $reputationAggregate->recordTransaction(Str::uuid()->toString(), 'failed', 50.0);
        $reputationAggregate->persist();

        AgentReputation::create([
            'reputation_id'           => $reputationId,
            'agent_id'                => $this->targetAgentId,
            'score'                   => $reputationAggregate->getScore(),
            'trust_level'             => $reputationAggregate->getTrustLevel(),
            'total_transactions'      => 3,
            'successful_transactions' => 2,
            'failed_transactions'     => 1,
            'disputed_transactions'   => 0,
        ]);

        $response = $this->getJson("/api/agents/{$this->targetDid}/reputation");

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertEquals(3, $data['total_transactions']);
        $this->assertEquals(2, $data['successful_transactions']);
        $this->assertEquals(1, $data['failed_transactions']);
        $this->assertGreaterThan(0, $data['success_rate']);
    }

    public function test_reputation_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/agents/did:example:unknown/reputation');

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Agent not found',
            ]);
    }
}
