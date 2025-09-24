<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\AgentProtocol\Aggregates\AgentComplianceAggregate;
use App\Domain\AgentProtocol\Enums\KycVerificationLevel;
use App\Models\Agent;
use Workflow\Activity;

class SetInitialTransactionLimitsActivity extends Activity
{
    /**
     * Set initial transaction limits based on verification level and risk score.
     */
    public function execute(string $agentId, string $verificationLevel, int $riskScore): array
    {
        $level = KycVerificationLevel::from($verificationLevel);
        $limits = $this->calculateTransactionLimits($level, $riskScore);

        // Update agent model with limits
        $agent = Agent::where('agent_id', $agentId)->first();
        if ($agent) {
            $agent->update([
                'daily_transaction_limit'   => $limits['daily'],
                'weekly_transaction_limit'  => $limits['weekly'],
                'monthly_transaction_limit' => $limits['monthly'],
                'limit_currency'            => 'USD',
                'limits_updated_at'         => now(),
            ]);
        }

        // Update the aggregate with transaction limits
        $aggregate = AgentComplianceAggregate::retrieve($agentId);
        $aggregate->setTransactionLimits($limits);
        $aggregate->persist();

        // Log the limits set
        logger()->info('Transaction limits set for agent', [
            'agent_id'           => $agentId,
            'verification_level' => $verificationLevel,
            'risk_score'         => $riskScore,
            'limits'             => $limits,
        ]);

        return [
            'success'        => true,
            'limits'         => $limits,
            'effectiveDate'  => now()->toIso8601String(),
            'nextReviewDate' => now()->addMonths(3)->toIso8601String(),
        ];
    }

    /**
     * Calculate transaction limits based on verification level and risk score.
     */
    private function calculateTransactionLimits(KycVerificationLevel $level, int $riskScore): array
    {
        // Base limits by verification level
        $baseLimits = $this->getBaseLimits($level);

        // Apply risk score multiplier
        $multiplier = $this->getRiskMultiplier($riskScore);

        // Calculate adjusted limits
        $limits = [
            'daily'   => round($baseLimits['daily'] * $multiplier, 2),
            'weekly'  => round($baseLimits['weekly'] * $multiplier, 2),
            'monthly' => round($baseLimits['monthly'] * $multiplier, 2),
        ];

        // Apply minimum limits
        $minimumLimits = $this->getMinimumLimits();
        $limits['daily'] = max($limits['daily'], $minimumLimits['daily']);
        $limits['weekly'] = max($limits['weekly'], $minimumLimits['weekly']);
        $limits['monthly'] = max($limits['monthly'], $minimumLimits['monthly']);

        // Apply maximum limits for the level
        $maximumLimits = $this->getMaximumLimits($level);
        $limits['daily'] = min($limits['daily'], $maximumLimits['daily']);
        $limits['weekly'] = min($limits['weekly'], $maximumLimits['weekly']);
        $limits['monthly'] = min($limits['monthly'], $maximumLimits['monthly']);

        // Ensure logical consistency (daily <= weekly <= monthly)
        $limits = $this->ensureLimitConsistency($limits);

        return $limits;
    }

    /**
     * Get base limits by verification level.
     */
    private function getBaseLimits(KycVerificationLevel $level): array
    {
        return match ($level) {
            KycVerificationLevel::BASIC => [
                'daily'   => 1000.00,
                'weekly'  => 5000.00,
                'monthly' => 10000.00,
            ],
            KycVerificationLevel::ENHANCED => [
                'daily'   => 5000.00,
                'weekly'  => 25000.00,
                'monthly' => 50000.00,
            ],
            KycVerificationLevel::FULL => [
                'daily'   => 10000.00,
                'weekly'  => 50000.00,
                'monthly' => 100000.00,
            ],
        };
    }

    /**
     * Get risk score multiplier.
     */
    private function getRiskMultiplier(int $riskScore): float
    {
        return match (true) {
            $riskScore <= 20 => 1.5,    // Low risk - 150% of base
            $riskScore <= 40 => 1.2,    // Medium-low risk - 120% of base
            $riskScore <= 60 => 1.0,    // Medium risk - 100% of base
            $riskScore <= 80 => 0.75,   // Medium-high risk - 75% of base
            default          => 0.5,              // High risk - 50% of base
        };
    }

    /**
     * Get minimum transaction limits.
     */
    private function getMinimumLimits(): array
    {
        return [
            'daily'   => 100.00,
            'weekly'  => 500.00,
            'monthly' => 1000.00,
        ];
    }

    /**
     * Get maximum limits by verification level.
     */
    private function getMaximumLimits(KycVerificationLevel $level): array
    {
        return match ($level) {
            KycVerificationLevel::BASIC => [
                'daily'   => 2000.00,
                'weekly'  => 10000.00,
                'monthly' => 20000.00,
            ],
            KycVerificationLevel::ENHANCED => [
                'daily'   => 10000.00,
                'weekly'  => 50000.00,
                'monthly' => 100000.00,
            ],
            KycVerificationLevel::FULL => [
                'daily'   => 50000.00,
                'weekly'  => 250000.00,
                'monthly' => 500000.00,
            ],
        };
    }

    /**
     * Ensure limit consistency (daily <= weekly <= monthly).
     */
    private function ensureLimitConsistency(array $limits): array
    {
        // Weekly should be at least daily
        if ($limits['weekly'] < $limits['daily']) {
            $limits['weekly'] = $limits['daily'] * 5; // Assume 5 business days
        }

        // Monthly should be at least weekly
        if ($limits['monthly'] < $limits['weekly']) {
            $limits['monthly'] = $limits['weekly'] * 4; // Assume 4 weeks
        }

        // Monthly should be at least daily * 20 (business days)
        if ($limits['monthly'] < $limits['daily'] * 20) {
            $limits['monthly'] = $limits['daily'] * 20;
        }

        return $limits;
    }
}
