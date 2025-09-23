<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\AgentProtocol\Services\ReputationService;
use Exception;
use Workflow\Activity;

class CalculateReputationScoreActivity extends Activity
{
    public function __construct(
        private readonly ReputationService $reputationService
    ) {
    }

    public function execute(
        string $agentId,
        string $eventType,
        array $eventData
    ): array {
        try {
            $currentReputation = $this->reputationService->getAgentReputation($agentId);
            $previousScore = $currentReputation->score;

            // Calculate score change based on event type
            $scoreChange = $this->calculateScoreChange($eventType, $eventData, $currentReputation);

            $newScore = max(0, min(100, $previousScore + $scoreChange));

            // Determine new trust level
            $newTrustLevel = $this->calculateTrustLevel($newScore);

            return [
                'success'             => true,
                'agent_id'            => $agentId,
                'previous_score'      => $previousScore,
                'new_score'           => $newScore,
                'score_change'        => $scoreChange,
                'trust_level'         => $newTrustLevel,
                'calculation_details' => [
                    'event_type' => $eventType,
                    'factors'    => $this->getCalculationFactors($eventType, $eventData),
                ],
            ];
        } catch (Exception $e) {
            return [
                'success'  => false,
                'error'    => $e->getMessage(),
                'agent_id' => $agentId,
            ];
        }
    }

    private function calculateScoreChange(string $eventType, array $eventData, $currentReputation): float
    {
        $baseChange = match ($eventType) {
            'transaction_success'    => $this->calculateTransactionSuccessBonus($eventData),
            'transaction_failed'     => $this->calculateTransactionFailurePenalty($eventData),
            'dispute_raised'         => $this->calculateDisputePenalty($eventData),
            'dispute_resolved'       => $this->calculateDisputeResolutionBonus($eventData),
            'positive_feedback'      => $this->calculateFeedbackBonus($eventData),
            'negative_feedback'      => $this->calculateFeedbackPenalty($eventData),
            'verification_completed' => 5.0,
            'violation_reported'     => -10.0,
            default                  => 0.0,
        };

        // Apply diminishing returns for high scores
        if ($currentReputation->score > 80) {
            $baseChange = $baseChange * 0.5;
        }

        // Apply acceleration for recovery from low scores
        if ($currentReputation->score < 30 && $baseChange > 0) {
            $baseChange = $baseChange * 1.5;
        }

        return round($baseChange, 2);
    }

    private function calculateTransactionSuccessBonus(array $eventData): float
    {
        $value = $eventData['transaction_value'] ?? 0;
        $bonus = log10($value + 1) * 0.5; // Logarithmic scaling

        // Bonus for consecutive successes
        if (($eventData['consecutive_successes'] ?? 0) > 5) {
            $bonus *= 1.2;
        }

        return min($bonus, 3.0); // Cap at 3 points
    }

    private function calculateTransactionFailurePenalty(array $eventData): float
    {
        $value = $eventData['transaction_value'] ?? 0;
        $penalty = -log10($value + 1) * 1.5; // Higher penalty than bonus

        // Additional penalty for repeated failures
        if (($eventData['consecutive_failures'] ?? 0) > 3) {
            $penalty *= 1.5;
        }

        return max($penalty, -5.0); // Cap at -5 points
    }

    private function calculateDisputePenalty(array $eventData): float
    {
        $severity = $eventData['severity'] ?? 'minor';

        return match ($severity) {
            'minor'    => -5.0,
            'moderate' => -10.0,
            'major'    => -15.0,
            'critical' => -25.0,
            default    => -10.0,
        };
    }

    private function calculateDisputeResolutionBonus(array $eventData): float
    {
        $outcome = $eventData['outcome'] ?? 'neutral';

        return match ($outcome) {
            'favorable'   => 3.0,
            'neutral'     => 0.0,
            'unfavorable' => -2.0,
            default       => 0.0,
        };
    }

    private function calculateFeedbackBonus(array $eventData): float
    {
        $rating = $eventData['rating'] ?? 3;

        return ($rating - 3) * 0.5; // 5-star = +1, 4-star = +0.5, etc.
    }

    private function calculateFeedbackPenalty(array $eventData): float
    {
        $rating = $eventData['rating'] ?? 3;

        return ($rating - 3) * 1.0; // Double weight for negative feedback
    }

    private function calculateTrustLevel(float $score): string
    {
        return match (true) {
            $score >= 80 => 'trusted',
            $score >= 60 => 'high',
            $score >= 40 => 'neutral',
            $score >= 20 => 'low',
            default      => 'untrusted',
        };
    }

    private function getCalculationFactors(string $eventType, array $eventData): array
    {
        return [
            'event_type'         => $eventType,
            'transaction_value'  => $eventData['transaction_value'] ?? null,
            'severity'           => $eventData['severity'] ?? null,
            'rating'             => $eventData['rating'] ?? null,
            'consecutive_events' => $eventData['consecutive_successes'] ?? $eventData['consecutive_failures'] ?? 0,
            'time_factor'        => $eventData['time_since_last_event'] ?? null,
        ];
    }
}
