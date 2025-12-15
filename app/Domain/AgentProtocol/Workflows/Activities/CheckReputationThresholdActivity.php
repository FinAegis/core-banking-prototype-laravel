<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\AgentProtocol\DataObjects\ReputationScore;
use App\Domain\AgentProtocol\Models\AgentIdentity;
use Illuminate\Support\Facades\Log;
use Workflow\Activity;

/**
 * Activity that checks if an agent's reputation has crossed important thresholds.
 */
class CheckReputationThresholdActivity extends Activity
{
    // Reputation level thresholds
    private const THRESHOLDS = [
        'trusted'   => 80,
        'verified'  => 60,
        'neutral'   => 40,
        'cautious'  => 20,
        'untrusted' => 0,
    ];

    // Privilege thresholds
    private const PRIVILEGE_THRESHOLDS = [
        'instant_settlement'      => 75,
        'high_value_transactions' => 70,
        'escrow_free'             => 85,
        'api_rate_increase'       => 65,
    ];

    public function execute(
        string $agentId,
        ReputationScore $currentScore,
        ?ReputationScore $previousScore = null
    ): array {
        $result = [
            'agent_id'           => $agentId,
            'current_score'      => $currentScore->score,
            'current_level'      => $this->determineLevel($currentScore->score),
            'level_changed'      => false,
            'privileges_changed' => [],
            'alerts'             => [],
            'actions_required'   => [],
        ];

        // Check if level changed
        if ($previousScore !== null) {
            $previousLevel = $this->determineLevel($previousScore->score);
            $currentLevel = $this->determineLevel($currentScore->score);

            if ($previousLevel !== $currentLevel) {
                $result['level_changed'] = true;
                $result['previous_level'] = $previousLevel;
                $result['new_level'] = $currentLevel;

                // Determine direction
                $levelOrder = array_keys(self::THRESHOLDS);
                $previousIndex = array_search($previousLevel, $levelOrder);
                $currentIndex = array_search($currentLevel, $levelOrder);

                $result['direction'] = $currentIndex < $previousIndex ? 'upgraded' : 'downgraded';

                // Generate appropriate alerts
                if ($result['direction'] === 'downgraded') {
                    $result['alerts'][] = [
                        'type'     => 'level_downgrade',
                        'severity' => $currentLevel === 'untrusted' ? 'critical' : 'warning',
                        'message'  => "Agent reputation downgraded from {$previousLevel} to {$currentLevel}",
                    ];
                } else {
                    $result['alerts'][] = [
                        'type'     => 'level_upgrade',
                        'severity' => 'info',
                        'message'  => "Agent reputation upgraded from {$previousLevel} to {$currentLevel}",
                    ];
                }
            }
        }

        // Check privilege thresholds
        foreach (self::PRIVILEGE_THRESHOLDS as $privilege => $threshold) {
            $currentlyHas = $currentScore->score >= $threshold;
            $previouslyHad = $previousScore !== null && $previousScore->score >= $threshold;

            if ($currentlyHas !== $previouslyHad) {
                $result['privileges_changed'][] = [
                    'privilege' => $privilege,
                    'action'    => $currentlyHas ? 'granted' : 'revoked',
                    'threshold' => $threshold,
                ];

                if (! $currentlyHas) {
                    $result['alerts'][] = [
                        'type'     => 'privilege_revoked',
                        'severity' => 'warning',
                        'message'  => "Privilege '{$privilege}' has been revoked due to reputation drop",
                    ];
                }
            }
        }

        // Check for critical thresholds
        if ($currentScore->score < 20) {
            $result['alerts'][] = [
                'type'     => 'critical_reputation',
                'severity' => 'critical',
                'message'  => 'Agent reputation is critically low',
            ];
            $result['actions_required'][] = 'manual_review';
        }

        // Check for rapid decline
        if ($previousScore !== null) {
            $decline = $previousScore->score - $currentScore->score;
            if ($decline > 20) {
                $result['alerts'][] = [
                    'type'     => 'rapid_decline',
                    'severity' => 'warning',
                    'message'  => "Reputation declined by {$decline} points rapidly",
                ];
                $result['actions_required'][] = 'investigate_activity';
            }
        }

        // Update agent metadata with current privileges
        $this->updateAgentPrivileges($agentId, $currentScore->score);

        Log::info('Reputation threshold check completed', [
            'agent_id'      => $agentId,
            'level_changed' => $result['level_changed'],
            'alerts_count'  => count($result['alerts']),
        ]);

        return $result;
    }

    private function determineLevel(float $score): string
    {
        foreach (self::THRESHOLDS as $level => $threshold) {
            if ($score >= $threshold) {
                return $level;
            }
        }

        return 'untrusted';
    }

    private function updateAgentPrivileges(string $agentId, float $score): void
    {
        $agent = AgentIdentity::where('did', $agentId)->first();

        if (! $agent) {
            return;
        }

        $privileges = [];
        foreach (self::PRIVILEGE_THRESHOLDS as $privilege => $threshold) {
            $privileges[$privilege] = $score >= $threshold;
        }

        $metadata = $agent->metadata ?? [];
        $metadata['privileges'] = $privileges;
        $metadata['privilege_updated_at'] = now()->toIso8601String();

        $agent->update(['metadata' => $metadata]);
    }
}
