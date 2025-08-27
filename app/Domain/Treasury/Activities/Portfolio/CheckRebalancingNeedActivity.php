<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Activities\Portfolio;

use App\Domain\Treasury\Services\PortfolioManagementService;
use App\Domain\Treasury\Services\RebalancingService;
use Exception;
use Log;
use RuntimeException;
use Workflow\Activity;

class CheckRebalancingNeedActivity extends Activity
{
    public function __construct(
        private readonly RebalancingService $rebalancingService,
        private readonly PortfolioManagementService $portfolioService
    ) {
    }

    public function execute(array $input): array
    {
        $portfolioId = $input['portfolio_id'];
        $reason = $input['reason'] ?? 'scheduled_check';
        $overrides = $input['overrides'] ?? [];

        try {
            // Get current portfolio state
            $portfolio = $this->portfolioService->getPortfolio($portfolioId);

            // Check if portfolio is already rebalancing
            if ($portfolio['is_rebalancing']) {
                return [
                    'needed'         => false,
                    'reason'         => 'Portfolio is already being rebalanced',
                    'drift_analysis' => [],
                    'current_status' => 'rebalancing_in_progress',
                ];
            }

            // Force rebalancing if explicitly requested via overrides
            if ($overrides['force_rebalancing'] ?? false) {
                return [
                    'needed'         => true,
                    'reason'         => 'Force rebalancing requested via overrides',
                    'drift_analysis' => $this->calculateDriftAnalysis($portfolio),
                    'current_status' => 'force_requested',
                    'override_used'  => true,
                ];
            }

            // Use the existing rebalancing service to check if rebalancing is needed
            $needsRebalancing = $this->rebalancingService->checkRebalancingNeeded($portfolioId);

            if (! $needsRebalancing) {
                return [
                    'needed'         => false,
                    'reason'         => 'Portfolio allocation is within acceptable drift thresholds',
                    'drift_analysis' => $this->calculateDriftAnalysis($portfolio),
                    'current_status' => 'balanced',
                ];
            }

            // Calculate detailed drift analysis
            $driftAnalysis = $this->calculateDriftAnalysis($portfolio);
            $drifts = array_column($driftAnalysis['allocations'], 'drift');
            $maxDrift = ! empty($drifts) ? max($drifts) : 0.0;
            $avgDrift = array_sum(array_column($driftAnalysis['allocations'], 'drift')) / count($driftAnalysis['allocations']);

            // Determine urgency level based on drift severity
            $urgency = $this->determineRebalancingUrgency($maxDrift, $avgDrift, $portfolio);

            return [
                'needed'         => true,
                'reason'         => $this->generateRebalancingReason($driftAnalysis, $reason),
                'drift_analysis' => $driftAnalysis,
                'current_status' => 'needs_rebalancing',
                'urgency'        => $urgency,
                'max_drift'      => $maxDrift,
                'average_drift'  => $avgDrift,
                'threshold'      => $portfolio['strategy']['rebalanceThreshold'] ?? 5.0,
            ];
        } catch (Exception $e) {
            Log::error('Failed to check rebalancing need', [
                'portfolio_id' => $portfolioId,
                'reason'       => $reason,
                'error'        => $e->getMessage(),
            ]);

            throw new RuntimeException(
                "Failed to check rebalancing need for portfolio {$portfolioId}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Calculate detailed drift analysis for all asset allocations.
     */
    private function calculateDriftAnalysis(array $portfolio): array
    {
        $allocations = [];
        $totalValue = $portfolio['total_value'];
        $threshold = $portfolio['strategy']['rebalanceThreshold'] ?? 5.0;

        foreach ($portfolio['asset_allocations'] as $allocation) {
            $currentWeight = $allocation['currentWeight'] ?? 0.0;
            $targetWeight = $allocation['targetWeight'] ?? 0.0;
            $drift = abs($currentWeight - $targetWeight);
            $currentValue = ($currentWeight / 100) * $totalValue;
            $targetValue = ($targetWeight / 100) * $totalValue;

            $allocations[] = [
                'asset_class'       => $allocation['assetClass'] ?? 'unknown',
                'current_weight'    => $currentWeight,
                'target_weight'     => $targetWeight,
                'drift'             => $drift,
                'current_value'     => $currentValue,
                'target_value'      => $targetValue,
                'value_diff'        => $targetValue - $currentValue,
                'exceeds_threshold' => $drift > $threshold,
                'severity'          => $this->getDriftSeverity($drift, $threshold),
            ];
        }

        // Sort by drift level (highest first)
        usort($allocations, fn ($a, $b) => $b['drift'] <=> $a['drift']);

        return [
            'total_value' => $totalValue,
            'threshold'   => $threshold,
            'allocations' => $allocations,
            'summary'     => [
                'total_allocations'   => count($allocations),
                'exceeding_threshold' => count(array_filter($allocations, fn ($a) => $a['exceeds_threshold'])),
                'max_drift'           => ! empty($allocations) ? max(array_column($allocations, 'drift')) : 0,
                'avg_drift'           => array_sum(array_column($allocations, 'drift')) / count($allocations),
            ],
        ];
    }

    /**
     * Determine the urgency level of rebalancing based on drift metrics.
     */
    private function determineRebalancingUrgency(float $maxDrift, float $avgDrift, array $portfolio): string
    {
        $threshold = $portfolio['strategy']['rebalanceThreshold'] ?? 5.0;

        // Critical - requires immediate attention
        if ($maxDrift > $threshold * 3 || $avgDrift > $threshold * 2) {
            return 'critical';
        }

        // High - should be addressed within 24 hours
        if ($maxDrift > $threshold * 2 || $avgDrift > $threshold * 1.5) {
            return 'high';
        }

        // Medium - should be addressed within a few days
        if ($maxDrift > $threshold * 1.5 || $avgDrift > $threshold) {
            return 'medium';
        }

        // Low - can be scheduled for next regular rebalancing
        return 'low';
    }

    /**
     * Generate a human-readable reason for rebalancing.
     */
    private function generateRebalancingReason(array $driftAnalysis, string $originalReason): string
    {
        $summary = $driftAnalysis['summary'];
        $maxDrift = $summary['max_drift'];
        $exceedingCount = $summary['exceeding_threshold'];
        $threshold = $driftAnalysis['threshold'];

        $reasons = [];

        if ($exceedingCount > 0) {
            $reasons[] = "{$exceedingCount} asset allocation(s) exceed the {$threshold}% drift threshold";
        }

        if ($maxDrift > $threshold * 2) {
            $reasons[] = "Maximum drift of {$maxDrift}% is significantly above threshold";
        }

        $baseReason = match ($originalReason) {
            'scheduled_check' => 'Scheduled rebalancing analysis',
            'drift_alert'     => 'Drift alert triggered',
            'manual_request'  => 'Manual rebalancing requested',
            'risk_review'     => 'Risk review triggered rebalancing',
            default           => ucfirst(str_replace('_', ' ', $originalReason)),
        };

        if (empty($reasons)) {
            return $baseReason . ' - rebalancing needed';
        }

        return $baseReason . ': ' . implode(', ', $reasons);
    }

    /**
     * Categorize drift severity level.
     */
    private function getDriftSeverity(float $drift, float $threshold): string
    {
        if ($drift > $threshold * 3) {
            return 'critical';
        }
        if ($drift > $threshold * 2) {
            return 'high';
        }
        if ($drift > $threshold) {
            return 'medium';
        }
        if ($drift > $threshold * 0.5) {
            return 'low';
        }

        return 'minimal';
    }
}
