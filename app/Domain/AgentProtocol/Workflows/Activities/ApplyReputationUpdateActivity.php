<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\AgentProtocol\DataObjects\ReputationScore;
use App\Domain\AgentProtocol\Services\ReputationService;
use Exception;
use InvalidArgumentException;
use Workflow\Activity;

class ApplyReputationUpdateActivity extends Activity
{
    public function __construct(
        private readonly ReputationService $reputationService
    ) {
    }

    public function execute(
        string $agentId,
        ?ReputationScore $newScore,
        float $scoreChange,
        string $eventType,
        array $eventData
    ): array {
        try {
            // Apply the reputation update based on event type
            $update = match ($eventType) {
                'transaction' => $this->reputationService->updateReputationFromTransaction(
                    $agentId,
                    $eventData['transaction_id'] ?? uniqid('tx_'),
                    $eventData['outcome'] ?? 'success',
                    $eventData['transaction_value'] ?? 0.0
                ),
                'dispute' => $this->reputationService->applyDisputePenalty(
                    $agentId,
                    $eventData['dispute_id'] ?? uniqid('dispute_'),
                    $eventData['severity'] ?? 'moderate',
                    $eventData['reason'] ?? 'Dispute raised'
                ),
                'boost' => $this->reputationService->boostReputation(
                    $agentId,
                    $eventData['reason'] ?? 'Manual boost',
                    abs($scoreChange)
                ),
                'decay' => $this->applyDecay($agentId, $scoreChange),
                default => null,
            };

            if ($update === null) {
                throw new InvalidArgumentException("Unknown event type: {$eventType}");
            }

            return [
                'success'    => true,
                'agent_id'   => $agentId,
                'update'     => $update->toArray(),
                'applied_at' => now()->toIso8601String(),
            ];
        } catch (Exception $e) {
            return [
                'success'   => false,
                'agent_id'  => $agentId,
                'error'     => $e->getMessage(),
                'failed_at' => now()->toIso8601String(),
            ];
        }
    }

    private function applyDecay(string $agentId, float $decayAmount): mixed
    {
        // For decay, we use the transaction mechanism with a special outcome
        return $this->reputationService->updateReputationFromTransaction(
            $agentId,
            'decay_' . uniqid(),
            'decay',
            abs($decayAmount) * 100 // Convert to a transaction value that produces the desired decay
        );
    }
}
