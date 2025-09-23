<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Services;

use App\Domain\AgentProtocol\Aggregates\ReputationAggregate;
use App\Domain\AgentProtocol\DataObjects\ReputationScore;
use App\Domain\AgentProtocol\DataObjects\ReputationUpdate;
use App\Domain\AgentProtocol\Models\Agent;
use Carbon\Carbon;
use DomainException;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReputationService
{
    private const CACHE_TTL = 300; // 5 minutes

    private const REPUTATION_THRESHOLDS = [
        'minimum_for_escrow'             => 30.0,
        'minimum_for_high_value'         => 50.0,
        'minimum_for_instant_settlement' => 70.0,
    ];

    /**
     * Initialize reputation for a new agent.
     */
    public function initializeAgentReputation(string $agentId, float $initialScore = 50.0): ReputationScore
    {
        $reputationId = $this->generateReputationId($agentId);

        $aggregate = ReputationAggregate::initializeReputation(
            reputationId: $reputationId,
            agentId: $agentId,
            initialScore: $initialScore,
            metadata: [
                'initialized_at' => Carbon::now()->toIso8601String(),
                'source'         => 'system',
            ]
        );

        $aggregate->persist();

        Cache::forget($this->getCacheKey($agentId));

        Log::info('Agent reputation initialized', [
            'agent_id'      => $agentId,
            'reputation_id' => $reputationId,
            'initial_score' => $initialScore,
        ]);

        return $this->getAgentReputation($agentId);
    }

    /**
     * Get current reputation score for an agent.
     */
    public function getAgentReputation(string $agentId): ReputationScore
    {
        return Cache::remember(
            $this->getCacheKey($agentId),
            self::CACHE_TTL,
            fn () => $this->fetchReputationFromAggregate($agentId)
        );
    }

    /**
     * Update reputation based on transaction outcome.
     */
    public function updateReputationFromTransaction(
        string $agentId,
        string $transactionId,
        string $outcome,
        float $transactionValue
    ): ReputationUpdate {
        $reputationId = $this->generateReputationId($agentId);
        $aggregate = ReputationAggregate::retrieve($reputationId);

        if (! $aggregate->getAgentId()) {
            // Initialize if not exists
            $this->initializeAgentReputation($agentId);
            $aggregate = ReputationAggregate::retrieve($reputationId);
        }

        $previousScore = $aggregate->getScore();

        $aggregate->recordTransaction(
            transactionId: $transactionId,
            outcome: $outcome,
            value: $transactionValue,
            metadata: [
                'timestamp' => Carbon::now()->toIso8601String(),
                'weight'    => $this->calculateTransactionWeight($transactionValue),
            ]
        );

        $aggregate->persist();

        Cache::forget($this->getCacheKey($agentId));

        return ReputationUpdate::fromTransaction(
            agentId: $agentId,
            transactionId: $transactionId,
            previousScore: $previousScore,
            newScore: $aggregate->getScore(),
            outcome: $outcome,
            value: $transactionValue
        );
    }

    /**
     * Apply penalty for dispute.
     */
    public function applyDisputePenalty(
        string $agentId,
        string $disputeId,
        string $severity,
        string $reason
    ): ReputationUpdate {
        $reputationId = $this->generateReputationId($agentId);
        $aggregate = ReputationAggregate::retrieve($reputationId);

        if (! $aggregate->getAgentId()) {
            throw new DomainException("Reputation not found for agent: {$agentId}");
        }

        $previousScore = $aggregate->getScore();

        $aggregate->applyDisputePenalty(
            disputeId: $disputeId,
            severity: $severity,
            reason: $reason,
            metadata: [
                'timestamp'   => Carbon::now()->toIso8601String(),
                'enforced_by' => 'system',
            ]
        );

        $aggregate->persist();

        Cache::forget($this->getCacheKey($agentId));

        Log::warning('Dispute penalty applied to agent reputation', [
            'agent_id'       => $agentId,
            'dispute_id'     => $disputeId,
            'severity'       => $severity,
            'previous_score' => $previousScore,
            'new_score'      => $aggregate->getScore(),
        ]);

        return ReputationUpdate::fromDispute(
            agentId: $agentId,
            disputeId: $disputeId,
            previousScore: $previousScore,
            newScore: $aggregate->getScore(),
            severity: $severity,
            reason: $reason
        );
    }

    /**
     * Boost reputation for positive actions.
     */
    public function boostReputation(
        string $agentId,
        string $reason,
        float $amount
    ): ReputationUpdate {
        $reputationId = $this->generateReputationId($agentId);
        $aggregate = ReputationAggregate::retrieve($reputationId);

        if (! $aggregate->getAgentId()) {
            throw new DomainException("Reputation not found for agent: {$agentId}");
        }

        $previousScore = $aggregate->getScore();

        $aggregate->applyReputationBoost(
            reason: $reason,
            amount: $amount,
            metadata: [
                'timestamp' => Carbon::now()->toIso8601String(),
                'source'    => 'manual_boost',
            ]
        );

        $aggregate->persist();

        Cache::forget($this->getCacheKey($agentId));

        return new ReputationUpdate(
            agentId: $agentId,
            transactionId: 'boost_' . uniqid(),
            type: 'boost',
            previousScore: $previousScore,
            newScore: $aggregate->getScore(),
            scoreChange: $amount,
            reason: $reason,
            metadata: ['boost_amount' => $amount]
        );
    }

    /**
     * Process reputation decay for inactive agents.
     */
    public function processReputationDecay(): Collection
    {
        $inactiveAgents = $this->findInactiveAgents(30); // 30 days threshold
        $results = collect();

        foreach ($inactiveAgents as $agent) {
            try {
                $reputationId = $this->generateReputationId($agent->id);
                $aggregate = ReputationAggregate::retrieve($reputationId);

                if (! $aggregate->getAgentId()) {
                    continue;
                }

                $daysSinceLastActivity = Carbon::parse($agent->last_activity_at)
                    ->diffInDays(Carbon::now());

                $aggregate->decayReputation((int) $daysSinceLastActivity);
                $aggregate->persist();

                Cache::forget($this->getCacheKey($agent->id));

                $results->push([
                    'agent_id'      => $agent->id,
                    'days_inactive' => $daysSinceLastActivity,
                    'new_score'     => $aggregate->getScore(),
                ]);
            } catch (Exception $e) {
                Log::error('Failed to process reputation decay', [
                    'agent_id' => $agent->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Check if agent meets reputation threshold for operation.
     */
    public function meetsThreshold(string $agentId, string $operation): bool
    {
        $reputation = $this->getAgentReputation($agentId);

        $threshold = match ($operation) {
            'escrow'             => self::REPUTATION_THRESHOLDS['minimum_for_escrow'],
            'high_value'         => self::REPUTATION_THRESHOLDS['minimum_for_high_value'],
            'instant_settlement' => self::REPUTATION_THRESHOLDS['minimum_for_instant_settlement'],
            default              => 0.0,
        };

        return $reputation->score >= $threshold;
    }

    /**
     * Get reputation statistics for an agent.
     */
    public function getReputationStatistics(string $agentId): array
    {
        $reputationId = $this->generateReputationId($agentId);
        $aggregate = ReputationAggregate::retrieve($reputationId);

        if (! $aggregate->getAgentId()) {
            return [
                'exists'   => false,
                'agent_id' => $agentId,
            ];
        }

        $stats = $aggregate->getStats();

        return array_merge($stats, [
            'exists'        => true,
            'agent_id'      => $agentId,
            'current_score' => $aggregate->getScore(),
            'trust_level'   => $aggregate->getTrustLevel(),
            'thresholds'    => [
                'can_use_escrow'     => $this->meetsThreshold($agentId, 'escrow'),
                'can_high_value'     => $this->meetsThreshold($agentId, 'high_value'),
                'can_instant_settle' => $this->meetsThreshold($agentId, 'instant_settlement'),
            ],
        ]);
    }

    /**
     * Get reputation leaderboard.
     */
    public function getLeaderboard(int $limit = 10): Collection
    {
        return DB::table('agent_reputations')
            ->select('agent_id', 'score', 'trust_level', 'total_transactions')
            ->orderByDesc('score')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'agent_id'           => $row->agent_id,
                'score'              => (float) $row->score,
                'trust_level'        => $row->trust_level,
                'total_transactions' => (int) $row->total_transactions,
            ]);
    }

    /**
     * Calculate trust relationship between two agents.
     */
    public function calculateTrustRelationship(string $agentA, string $agentB): float
    {
        $reputationA = $this->getAgentReputation($agentA);
        $reputationB = $this->getAgentReputation($agentB);

        // Base trust on combined reputation scores
        $baseTrust = ($reputationA->score + $reputationB->score) / 2;

        // Check transaction history between agents
        $sharedTransactions = $this->getSharedTransactionCount($agentA, $agentB);

        // Boost trust based on successful shared transactions
        $trustBoost = min($sharedTransactions * 2, 20); // Max 20 point boost

        return min($baseTrust + $trustBoost, 100.0);
    }

    private function generateReputationId(string $agentId): string
    {
        return "reputation_{$agentId}";
    }

    private function getCacheKey(string $agentId): string
    {
        return "agent_reputation:{$agentId}";
    }

    private function fetchReputationFromAggregate(string $agentId): ReputationScore
    {
        $reputationId = $this->generateReputationId($agentId);
        $aggregate = ReputationAggregate::retrieve($reputationId);

        if (! $aggregate->getAgentId()) {
            // Return default score if not initialized
            return new ReputationScore(
                agentId: $agentId,
                score: 50.0,
                trustLevel: 'neutral',
                totalTransactions: 0,
                successfulTransactions: 0,
                failedTransactions: 0,
                disputedTransactions: 0,
                successRate: 0.0,
                lastActivityAt: null,
                metadata: ['status' => 'not_initialized']
            );
        }

        $stats = $aggregate->getStats();

        return new ReputationScore(
            agentId: $agentId,
            score: $aggregate->getScore(),
            trustLevel: $aggregate->getTrustLevel(),
            totalTransactions: $stats['total_transactions'],
            successfulTransactions: $stats['successful_transactions'],
            failedTransactions: $stats['failed_transactions'],
            disputedTransactions: $stats['disputed_transactions'],
            successRate: $stats['success_rate'],
            lastActivityAt: Carbon::now()->toIso8601String(),
            metadata: []
        );
    }

    private function calculateTransactionWeight(float $value): string
    {
        return match (true) {
            $value < 100   => 'small',
            $value < 1000  => 'medium',
            $value < 10000 => 'large',
            default        => 'xlarge',
        };
    }

    private function findInactiveAgents(int $daysThreshold): Collection
    {
        return Agent::where('last_activity_at', '<', Carbon::now()->subDays($daysThreshold))
            ->where('status', 'active')
            ->get();
    }

    private function getSharedTransactionCount(string $agentA, string $agentB): int
    {
        return DB::table('agent_transactions')
            ->where(function ($query) use ($agentA, $agentB) {
                $query->where('from_agent_id', $agentA)
                    ->where('to_agent_id', $agentB);
            })
            ->orWhere(function ($query) use ($agentA, $agentB) {
                $query->where('from_agent_id', $agentB)
                    ->where('to_agent_id', $agentA);
            })
            ->where('status', 'completed')
            ->count();
    }
}
