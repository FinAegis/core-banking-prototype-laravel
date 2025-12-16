<?php

declare(strict_types=1);

namespace Tests\Domain\AgentProtocol\Services;

use App\Domain\AgentProtocol\DataObjects\ReputationScore;
use App\Domain\AgentProtocol\DataObjects\ReputationUpdate;
use App\Domain\AgentProtocol\Models\Agent;
use App\Domain\AgentProtocol\Services\ReputationService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

class ReputationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReputationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->service = app(ReputationService::class);
    }

    // =============================================
    // Initialization Tests
    // =============================================

    public function test_initializes_agent_reputation_with_default_score(): void
    {
        $agent = Agent::factory()->create([
            'did' => 'did:agent:test:init:' . uniqid(),
        ]);

        $reputation = $this->service->initializeAgentReputation($agent->did);

        $this->assertInstanceOf(ReputationScore::class, $reputation);
        $this->assertEquals($agent->did, $reputation->agentId);
        $this->assertEquals(50.0, $reputation->score);
        $this->assertEquals('neutral', $reputation->trustLevel);
    }

    public function test_initializes_agent_reputation_with_custom_score(): void
    {
        $agent = Agent::factory()->create([
            'did' => 'did:agent:test:custom:' . uniqid(),
        ]);

        $reputation = $this->service->initializeAgentReputation($agent->did, 75.0);

        $this->assertInstanceOf(ReputationScore::class, $reputation);
        $this->assertEquals(75.0, $reputation->score);
        $this->assertEquals('high', $reputation->trustLevel);
    }

    // =============================================
    // Reputation Retrieval Tests
    // =============================================

    public function test_gets_default_reputation_for_uninitialized_agent(): void
    {
        $agent = Agent::factory()->create([
            'did' => 'did:agent:test:uninit:' . uniqid(),
        ]);

        $reputation = $this->service->getAgentReputation($agent->did);

        $this->assertInstanceOf(ReputationScore::class, $reputation);
        $this->assertEquals(50.0, $reputation->score); // Default from config
        $this->assertEquals('neutral', $reputation->trustLevel);
        $this->assertEquals(0, $reputation->totalTransactions);
    }

    public function test_gets_initialized_reputation(): void
    {
        $agent = Agent::factory()->create([
            'did' => 'did:agent:test:getinit:' . uniqid(),
        ]);

        // Initialize first
        $this->service->initializeAgentReputation($agent->did, 80.0);

        // Clear cache to force fetch
        Cache::flush();

        $reputation = $this->service->getAgentReputation($agent->did);

        $this->assertInstanceOf(ReputationScore::class, $reputation);
        $this->assertEquals(80.0, $reputation->score);
    }

    // =============================================
    // Reputation Update Tests
    // =============================================

    public function test_updates_reputation_for_successful_transaction(): void
    {
        $agent = Agent::factory()->create([
            'did' => 'did:agent:test:txsuccess:' . uniqid(),
        ]);

        // Initialize
        $this->service->initializeAgentReputation($agent->did, 50.0);

        $result = $this->service->updateReputationFromTransaction(
            $agent->did,
            'tx_' . uniqid(),
            'completed',
            1000.0
        );

        $this->assertInstanceOf(ReputationUpdate::class, $result);
        $this->assertEquals($agent->did, $result->agentId);
        $this->assertGreaterThanOrEqual(50.0, $result->newScore);
    }

    public function test_updates_reputation_for_failed_transaction(): void
    {
        $agent = Agent::factory()->create([
            'did' => 'did:agent:test:txfail:' . uniqid(),
        ]);

        // Initialize with higher score
        $this->service->initializeAgentReputation($agent->did, 80.0);

        $result = $this->service->updateReputationFromTransaction(
            $agent->did,
            'tx_' . uniqid(),
            'failed',
            1000.0
        );

        $this->assertInstanceOf(ReputationUpdate::class, $result);
        $this->assertLessThanOrEqual(80.0, $result->newScore);
    }

    // =============================================
    // Dispute Penalty Tests
    // =============================================

    public function test_applies_dispute_penalty(): void
    {
        $agent = Agent::factory()->create([
            'did' => 'did:agent:test:dispute:' . uniqid(),
        ]);

        $this->service->initializeAgentReputation($agent->did, 80.0);

        $result = $this->service->applyDisputePenalty(
            $agent->did,
            'dispute_' . uniqid(),
            'medium',
            'buyer_complaint'
        );

        $this->assertInstanceOf(ReputationUpdate::class, $result);
        $this->assertLessThan(80.0, $result->newScore);
    }

    public function test_dispute_penalty_throws_for_uninitialized_agent(): void
    {
        $this->expectException(DomainException::class);

        $this->service->applyDisputePenalty(
            'did:agent:nonexistent:' . uniqid(),
            'dispute_' . uniqid(),
            'medium',
            'buyer_complaint'
        );
    }

    // =============================================
    // Reputation Boost Tests
    // =============================================

    public function test_boosts_reputation(): void
    {
        $agent = Agent::factory()->create([
            'did' => 'did:agent:test:boost:' . uniqid(),
        ]);

        $this->service->initializeAgentReputation($agent->did, 70.0);

        $result = $this->service->boostReputation(
            $agent->did,
            'verified_identity',
            10.0
        );

        $this->assertInstanceOf(ReputationUpdate::class, $result);
        $this->assertGreaterThan(70.0, $result->newScore);
    }

    public function test_boost_throws_for_uninitialized_agent(): void
    {
        $this->expectException(DomainException::class);

        $this->service->boostReputation(
            'did:agent:nonexistent:' . uniqid(),
            'verified_identity',
            10.0
        );
    }

    // =============================================
    // Threshold Tests
    // =============================================

    public function test_meets_threshold_for_high_reputation(): void
    {
        $agent = Agent::factory()->create([
            'did' => 'did:agent:test:threshold:' . uniqid(),
        ]);

        $this->service->initializeAgentReputation($agent->did, 85.0);

        // Score 85 should meet all thresholds (escrow=40, high_value=60, instant_settlement=80)
        $this->assertTrue($this->service->meetsThreshold($agent->did, 'escrow'));
        $this->assertTrue($this->service->meetsThreshold($agent->did, 'high_value'));
        $this->assertTrue($this->service->meetsThreshold($agent->did, 'instant_settlement'));
    }

    public function test_does_not_meet_threshold_for_low_reputation(): void
    {
        $agent = Agent::factory()->create([
            'did' => 'did:agent:test:lowthresh:' . uniqid(),
        ]);

        $this->service->initializeAgentReputation($agent->did, 25.0);

        // Score 25 should not meet any thresholds (escrow=40, high_value=60, instant_settlement=80)
        $this->assertFalse($this->service->meetsThreshold($agent->did, 'escrow'));
        $this->assertFalse($this->service->meetsThreshold($agent->did, 'high_value'));
        $this->assertFalse($this->service->meetsThreshold($agent->did, 'instant_settlement'));
    }

    // =============================================
    // Statistics Tests
    // =============================================

    public function test_gets_reputation_statistics_for_initialized_agent(): void
    {
        $agent = Agent::factory()->create([
            'did' => 'did:agent:test:stats:' . uniqid(),
        ]);

        $this->service->initializeAgentReputation($agent->did, 75.0);

        $stats = $this->service->getReputationStatistics($agent->did);

        $this->assertIsArray($stats);
        $this->assertTrue($stats['exists']);
        $this->assertArrayHasKey('current_score', $stats);
        $this->assertArrayHasKey('trust_level', $stats);
    }

    public function test_gets_reputation_statistics_for_uninitialized_agent(): void
    {
        $stats = $this->service->getReputationStatistics('did:agent:nonexistent:' . uniqid());

        $this->assertIsArray($stats);
        $this->assertFalse($stats['exists']);
    }

    // =============================================
    // Leaderboard Tests
    // =============================================

    public function test_gets_leaderboard(): void
    {
        // Create multiple agents with different scores
        for ($i = 0; $i < 5; $i++) {
            $agent = Agent::factory()->create([
                'did' => 'did:agent:test:leader:' . $i . ':' . uniqid(),
            ]);

            $this->service->initializeAgentReputation($agent->did, 50.0 + ($i * 10));
        }

        $leaderboard = $this->service->getLeaderboard(3);

        $this->assertInstanceOf(Collection::class, $leaderboard);
        $this->assertLessThanOrEqual(3, $leaderboard->count());
    }

    // =============================================
    // Trust Relationship Tests
    // =============================================

    public function test_calculates_trust_relationship(): void
    {
        $agent1 = Agent::factory()->create([
            'did' => 'did:agent:test:trust1:' . uniqid(),
        ]);
        $agent2 = Agent::factory()->create([
            'did' => 'did:agent:test:trust2:' . uniqid(),
        ]);

        $this->service->initializeAgentReputation($agent1->did, 80.0);
        $this->service->initializeAgentReputation($agent2->did, 70.0);

        $trust = $this->service->calculateTrustRelationship(
            $agent1->did,
            $agent2->did
        );

        $this->assertGreaterThan(0, $trust);
        $this->assertLessThanOrEqual(100.0, $trust);
    }

    // =============================================
    // Decay Tests
    // =============================================

    public function test_processes_reputation_decay(): void
    {
        $result = $this->service->processReputationDecay();

        $this->assertInstanceOf(Collection::class, $result);
    }

    // =============================================
    // Caching Tests
    // =============================================

    public function test_caches_reputation_data(): void
    {
        $agent = Agent::factory()->create([
            'did' => 'did:agent:test:cache:' . uniqid(),
        ]);

        $this->service->initializeAgentReputation($agent->did, 75.0);

        // First call
        $reputation1 = $this->service->getAgentReputation($agent->did);

        // Second call - should use cache
        $reputation2 = $this->service->getAgentReputation($agent->did);

        $this->assertEquals($reputation1->score, $reputation2->score);
        $this->assertEquals($reputation1->agentId, $reputation2->agentId);
    }

    // =============================================
    // ReputationScore Data Object Tests
    // =============================================

    public function test_reputation_score_from_array(): void
    {
        $data = [
            'agent_id'                => 'did:agent:test:dto:123',
            'score'                   => 75.0,
            'trust_level'             => 'high',
            'total_transactions'      => 100,
            'successful_transactions' => 90,
            'failed_transactions'     => 8,
            'disputed_transactions'   => 2,
            'success_rate'            => 90.0,
            'last_activity_at'        => now()->toIso8601String(),
            'metadata'                => ['verified' => true],
        ];

        $score = ReputationScore::fromArray($data);

        $this->assertEquals($data['agent_id'], $score->agentId);
        $this->assertEquals($data['score'], $score->score);
        $this->assertEquals($data['trust_level'], $score->trustLevel);
    }

    public function test_reputation_score_to_array(): void
    {
        $score = new ReputationScore(
            agentId: 'did:agent:test:toarray:123',
            score: 80.0,
            trustLevel: 'high',
            totalTransactions: 50,
            successfulTransactions: 45,
            failedTransactions: 4,
            disputedTransactions: 1,
            successRate: 90.0
        );

        $array = $score->toArray();

        $this->assertArrayHasKey('agent_id', $array);
        $this->assertArrayHasKey('score', $array);
        $this->assertArrayHasKey('trust_level', $array);
        $this->assertEquals(80.0, $array['score']);
    }

    public function test_reputation_score_high_risk_detection(): void
    {
        $highRisk = new ReputationScore(
            agentId: 'did:agent:test:highrisk:123',
            score: 15.0,
            trustLevel: 'untrusted',
            totalTransactions: 10,
            successfulTransactions: 2,
            failedTransactions: 6,
            disputedTransactions: 2,
            successRate: 20.0
        );

        $this->assertTrue($highRisk->isHighRisk());

        $lowRisk = new ReputationScore(
            agentId: 'did:agent:test:lowrisk:123',
            score: 85.0,
            trustLevel: 'high',
            totalTransactions: 100,
            successfulTransactions: 95,
            failedTransactions: 4,
            disputedTransactions: 1,
            successRate: 95.0
        );

        $this->assertFalse($lowRisk->isHighRisk());
    }

    public function test_reputation_score_requires_manual_review(): void
    {
        $needsReview = new ReputationScore(
            agentId: 'did:agent:test:review:123',
            score: 40.0,
            trustLevel: 'low',
            totalTransactions: 20,
            successfulTransactions: 8,
            failedTransactions: 6,
            disputedTransactions: 6,
            successRate: 40.0
        );

        $this->assertTrue($needsReview->requiresManualReview());
    }

    public function test_reputation_score_validation(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ReputationScore(
            agentId: 'did:agent:test:invalid:123',
            score: 150.0, // Invalid: > 100
            trustLevel: 'high',
            totalTransactions: 0,
            successfulTransactions: 0,
            failedTransactions: 0,
            disputedTransactions: 0,
            successRate: 0.0
        );
    }

    public function test_reputation_score_invalid_trust_level(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ReputationScore(
            agentId: 'did:agent:test:badlevel:123',
            score: 50.0,
            trustLevel: 'invalid_level', // Invalid trust level
            totalTransactions: 0,
            successfulTransactions: 0,
            failedTransactions: 0,
            disputedTransactions: 0,
            successRate: 0.0
        );
    }

    // =============================================
    // ReputationUpdate Data Object Tests
    // =============================================

    public function test_reputation_update_from_transaction(): void
    {
        $update = ReputationUpdate::fromTransaction(
            agentId: 'did:agent:test:update:123',
            transactionId: 'tx_123',
            previousScore: 50.0,
            newScore: 55.0,
            outcome: 'completed',
            value: 1000.0
        );

        $this->assertEquals('transaction', $update->type);
        $this->assertEquals(5.0, $update->scoreChange);
        $this->assertEquals('did:agent:test:update:123', $update->agentId);
    }

    public function test_reputation_update_from_dispute(): void
    {
        $update = ReputationUpdate::fromDispute(
            agentId: 'did:agent:test:dispute:123',
            disputeId: 'dispute_123',
            previousScore: 80.0,
            newScore: 70.0,
            severity: 'high',
            reason: 'buyer_complaint'
        );

        $this->assertEquals('dispute', $update->type);
        $this->assertEquals(-10.0, $update->scoreChange);
        $this->assertEquals('buyer_complaint', $update->reason);
    }

    public function test_reputation_update_to_array(): void
    {
        $update = new ReputationUpdate(
            agentId: 'did:agent:test:array:123',
            transactionId: 'tx_123',
            type: 'transaction',
            previousScore: 50.0,
            newScore: 55.0,
            scoreChange: 5.0,
            reason: 'completed',
            metadata: ['test' => true]
        );

        $array = $update->toArray();

        $this->assertArrayHasKey('agent_id', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('score_change', $array);
        $this->assertEquals(5.0, $array['score_change']);
    }
}
