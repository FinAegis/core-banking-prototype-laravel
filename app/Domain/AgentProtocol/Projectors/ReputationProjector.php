<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Projectors;

use App\Domain\AgentProtocol\Events\ReputationDecayed;
use App\Domain\AgentProtocol\Events\ReputationDecreased;
use App\Domain\AgentProtocol\Events\ReputationIncreased;
use App\Domain\AgentProtocol\Events\TransactionScored;
use App\Domain\AgentProtocol\Events\TrustLevelChanged;
use App\Domain\AgentProtocol\Models\AgentReputation;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

/**
 * Projector to maintain Reputation read models from events.
 */
class ReputationProjector extends Projector
{
    /**
     * Handle reputation increased event.
     */
    public function onReputationIncreased(ReputationIncreased $event): void
    {
        $reputation = AgentReputation::where('agent_id', $event->agentId)->first();

        if (! $reputation) {
            $reputation = AgentReputation::create([
                'reputation_id'           => 'rep_' . $event->agentId,
                'agent_id'                => $event->agentId,
                'score'                   => 50.0, // Start with base score
                'trust_level'             => 'neutral',
                'total_transactions'      => 0,
                'successful_transactions' => 0,
                'failed_transactions'     => 0,
                'disputed_transactions'   => 0,
                'success_rate'            => 0.0,
            ]);
        }

        $reputation->score = min(100, $reputation->score + $event->points);
        $reputation->trust_level = $this->calculateTrustLevel($reputation->score);
        $reputation->save();
    }

    /**
     * Handle reputation decreased event.
     */
    public function onReputationDecreased(ReputationDecreased $event): void
    {
        $reputation = AgentReputation::where('agent_id', $event->agentId)->first();

        if ($reputation) {
            $reputation->score = max(0, $reputation->score - $event->points);
            $reputation->trust_level = $this->calculateTrustLevel($reputation->score);
            $reputation->save();
        }
    }

    /**
     * Handle reputation decay event.
     */
    public function onReputationDecayed(ReputationDecayed $event): void
    {
        $reputation = AgentReputation::where('agent_id', $event->agentId)->first();

        if ($reputation) {
            $reputation->score = $reputation->score * $event->decayFactor;
            $reputation->trust_level = $this->calculateTrustLevel($reputation->score);
            $reputation->last_decay_at = $event->decayedAt;
            $reputation->save();
        }
    }

    /**
     * Handle trust level change event.
     */
    public function onTrustLevelChanged(TrustLevelChanged $event): void
    {
        $reputation = AgentReputation::where('agent_id', $event->agentId)->first();

        if ($reputation) {
            // Ensure trust level is one of the valid enum values
            $validLevels = ['untrusted', 'low', 'neutral', 'high', 'trusted'];
            $trustLevel = in_array($event->newLevel, $validLevels, true)
                ? $event->newLevel
                : 'neutral';

            /** @var 'untrusted'|'low'|'neutral'|'high'|'trusted' $trustLevel */
            $reputation->trust_level = $trustLevel;
            $reputation->save();
        }
    }

    /**
     * Handle transaction scored event.
     */
    public function onTransactionScored(TransactionScored $event): void
    {
        $reputation = AgentReputation::where('agent_id', $event->agentId)->first();

        if (! $reputation) {
            $reputation = AgentReputation::create([
                'reputation_id'           => 'rep_' . $event->agentId,
                'agent_id'                => $event->agentId,
                'score'                   => 50.0,
                'trust_level'             => 'neutral',
                'total_transactions'      => 0,
                'successful_transactions' => 0,
                'failed_transactions'     => 0,
                'disputed_transactions'   => 0,
                'success_rate'            => 0.0,
            ]);
        }

        $reputation->total_transactions++;

        switch ($event->outcome) {
            case 'success':
                $reputation->successful_transactions++;
                $reputation->score = min(100, $reputation->score + 0.5);
                break;
            case 'failure':
                $reputation->failed_transactions++;
                $reputation->score = max(0, $reputation->score - 2.0);
                break;
            case 'dispute':
                $reputation->disputed_transactions++;
                $reputation->score = max(0, $reputation->score - 1.0);
                break;
        }

        // Recalculate success rate
        if ($reputation->total_transactions > 0) {
            $reputation->success_rate = ($reputation->successful_transactions / $reputation->total_transactions) * 100;
        }

        $reputation->trust_level = $this->calculateTrustLevel($reputation->score);
        $reputation->save();
    }

    /**
     * Calculate trust level based on score.
     *
     * @return 'untrusted'|'low'|'neutral'|'high'|'trusted'
     */
    private function calculateTrustLevel(float $score): string
    {
        if ($score >= 90) {
            return 'trusted';
        } elseif ($score >= 70) {
            return 'high';
        } elseif ($score >= 50) {
            return 'neutral';
        } elseif ($score >= 30) {
            return 'low';
        } else {
            return 'untrusted';
        }
    }
}
