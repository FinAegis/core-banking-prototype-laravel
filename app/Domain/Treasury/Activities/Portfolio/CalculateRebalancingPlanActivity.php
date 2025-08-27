<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Activities\Portfolio;

use App\Domain\Treasury\Services\RebalancingService;
use Exception;
use Log;
use RuntimeException;
use Workflow\Activity;

class CalculateRebalancingPlanActivity extends Activity
{
    public function __construct(
        private readonly RebalancingService $rebalancingService
    ) {
    }

    public function execute(array $input): array
    {
        $portfolioId = $input['portfolio_id'];
        $rebalanceId = $input['rebalance_id'];
        $reason = $input['reason'] ?? 'scheduled_rebalancing';
        $driftAnalysis = $input['drift_analysis'] ?? [];
        $overrides = $input['overrides'] ?? [];

        try {
            // Use the existing rebalancing service to calculate the detailed plan
            $rebalancingPlan = $this->rebalancingService->calculateRebalancingPlan($portfolioId);

            // Apply any overrides to the plan
            if (! empty($overrides)) {
                $rebalancingPlan = $this->applyOverrides($rebalancingPlan, $overrides);
            }

            // Enhance the plan with workflow-specific metadata
            $enhancedPlan = array_merge($rebalancingPlan, [
                'rebalance_id'      => $rebalanceId,
                'created_at'        => now()->toISOString(),
                'reason'            => $reason,
                'drift_analysis'    => $driftAnalysis,
                'workflow_metadata' => [
                    'requires_approval' => $this->requiresApproval($rebalancingPlan),
                    'execution_time'    => $this->calculateExecutionTime($rebalancingPlan),
                    'complexity_score'  => $this->calculateComplexityScore($rebalancingPlan),
                    'confidence_level'  => $this->calculateConfidenceLevel($rebalancingPlan),
                    'risk_assessment'   => $this->assessExecutionRisk($rebalancingPlan),
                ],
                'validation' => $this->validatePlan($rebalancingPlan),
            ]);

            // Log plan creation for audit trail
            Log::info('Rebalancing plan calculated', [
                'portfolio_id'      => $portfolioId,
                'rebalance_id'      => $rebalanceId,
                'action_count'      => count($enhancedPlan['actions']),
                'total_cost'        => $enhancedPlan['total_transaction_cost'],
                'requires_approval' => $enhancedPlan['workflow_metadata']['requires_approval'],
                'complexity_score'  => $enhancedPlan['workflow_metadata']['complexity_score'],
            ]);

            return $enhancedPlan;
        } catch (Exception $e) {
            Log::error('Failed to calculate rebalancing plan', [
                'portfolio_id' => $portfolioId,
                'rebalance_id' => $rebalanceId,
                'error'        => $e->getMessage(),
            ]);

            throw new RuntimeException(
                "Failed to calculate rebalancing plan for portfolio {$portfolioId}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Apply any overrides to the calculated rebalancing plan.
     */
    private function applyOverrides(array $plan, array $overrides): array
    {
        // Override specific asset allocations if provided
        if (isset($overrides['asset_overrides'])) {
            foreach ($overrides['asset_overrides'] as $assetClass => $overrideData) {
                foreach ($plan['actions'] as &$action) {
                    if ($action['asset_class'] === $assetClass) {
                        if (isset($overrideData['target_weight'])) {
                            $action['target_weight'] = $overrideData['target_weight'];
                            $action['target_value'] = ($overrideData['target_weight'] / 100) * $plan['total_portfolio_value'];
                            $action['difference'] = $action['target_value'] - $action['current_value'];
                            $action['action_type'] = $action['difference'] > 0 ? 'buy' : 'sell';
                            $action['amount'] = abs($action['difference']);
                        }

                        if (isset($overrideData['max_amount'])) {
                            $action['amount'] = min($action['amount'], $overrideData['max_amount']);
                        }

                        $action['override_applied'] = true;
                    }
                }
            }
        }

        // Override transaction cost limits
        if (isset($overrides['max_transaction_cost'])) {
            $plan['max_transaction_cost_override'] = $overrides['max_transaction_cost'];
        }

        // Override execution timing
        if (isset($overrides['execution_schedule'])) {
            $plan['execution_schedule'] = $overrides['execution_schedule'];
        }

        // Recalculate totals after overrides
        $plan['total_transaction_cost'] = array_sum(array_column($plan['actions'], 'transaction_cost'));
        $plan['overrides_applied'] = true;

        return $plan;
    }

    /**
     * Determine if the plan requires manual approval.
     */
    private function requiresApproval(array $plan): bool
    {
        // Large transaction costs
        if (($plan['total_transaction_cost'] ?? 0) > 10000) {
            return true;
        }

        // High risk impact
        if (($plan['risk_impact'] ?? '') === 'high_risk_reduction') {
            return true;
        }

        // Many actions to execute
        if (count($plan['actions'] ?? []) > 5) {
            return true;
        }

        // Large percentage of portfolio being rebalanced
        $totalRebalanceValue = array_sum(array_column($plan['actions'] ?? [], 'amount'));
        $portfolioValue = $plan['total_portfolio_value'] ?? 0;

        if ($portfolioValue > 0 && ($totalRebalanceValue / $portfolioValue) > 0.2) {
            return true;
        }

        return false;
    }

    /**
     * Calculate estimated execution time in minutes.
     */
    private function calculateExecutionTime(array $plan): int
    {
        $baseTime = 10; // Base time for setup and validation
        $actionTime = count($plan['actions'] ?? []) * 5; // 5 minutes per action
        $complexityTime = $this->calculateComplexityScore($plan) * 2; // Additional time for complexity

        return $baseTime + $actionTime + $complexityTime;
    }

    /**
     * Calculate complexity score from 1-10 based on plan characteristics.
     */
    private function calculateComplexityScore(array $plan): int
    {
        $score = 1;

        // Number of actions increases complexity
        $actionCount = count($plan['actions'] ?? []);
        $score += min($actionCount, 5);

        // Large transaction amounts increase complexity
        $totalCost = $plan['total_transaction_cost'] ?? 0;
        if ($totalCost > 50000) {
            $score += 3;
        } elseif ($totalCost > 20000) {
            $score += 2;
        } elseif ($totalCost > 5000) {
            $score += 1;
        }

        // Risk impact increases complexity
        $riskImpact = $plan['risk_impact'] ?? 'low_risk_reduction';
        $score += match ($riskImpact) {
            'high_risk_reduction'     => 2,
            'moderate_risk_reduction' => 1,
            default                   => 0,
        };

        return min($score, 10);
    }

    /**
     * Calculate confidence level in plan execution success.
     */
    private function calculateConfidenceLevel(array $plan): float
    {
        $confidence = 0.95; // Start with high confidence

        // Reduce confidence for high complexity
        $complexityScore = $this->calculateComplexityScore($plan);
        $confidence -= ($complexityScore - 1) * 0.05;

        // Reduce confidence for large transaction costs
        $totalCost = $plan['total_transaction_cost'] ?? 0;
        if ($totalCost > 100000) {
            $confidence -= 0.15;
        } elseif ($totalCost > 50000) {
            $confidence -= 0.10;
        } elseif ($totalCost > 20000) {
            $confidence -= 0.05;
        }

        // Reduce confidence if not recommended by the service
        if (! ($plan['recommended'] ?? true)) {
            $confidence -= 0.20;
        }

        return max($confidence, 0.5); // Never go below 50% confidence
    }

    /**
     * Assess execution risk level.
     */
    private function assessExecutionRisk(array $plan): string
    {
        $riskFactors = 0;

        // High transaction cost is a risk factor
        if (($plan['total_transaction_cost'] ?? 0) > 50000) {
            $riskFactors += 2;
        } elseif (($plan['total_transaction_cost'] ?? 0) > 20000) {
            $riskFactors += 1;
        }

        // Many actions increase risk
        if (count($plan['actions'] ?? []) > 7) {
            $riskFactors += 2;
        } elseif (count($plan['actions'] ?? []) > 3) {
            $riskFactors += 1;
        }

        // Low net benefit is a risk factor
        if (($plan['net_benefit'] ?? 0) < 0) {
            $riskFactors += 2;
        } elseif (($plan['net_benefit'] ?? 0) < 1000) {
            $riskFactors += 1;
        }

        return match ($riskFactors) {
            0, 1 => 'low',
            2, 3 => 'medium',
            4, 5 => 'high',
            default => 'critical',
        };
    }

    /**
     * Validate the rebalancing plan for completeness and consistency.
     */
    private function validatePlan(array $plan): array
    {
        $issues = [];
        $warnings = [];

        // Check for required fields
        if (empty($plan['actions'])) {
            $issues[] = 'No rebalancing actions defined';
        }

        if (! isset($plan['total_portfolio_value']) || $plan['total_portfolio_value'] <= 0) {
            $issues[] = 'Invalid total portfolio value';
        }

        // Check action consistency
        foreach ($plan['actions'] ?? [] as $index => $action) {
            if (empty($action['asset_class'])) {
                $issues[] = "Action {$index}: Missing asset class";
            }

            if (! isset($action['amount']) || $action['amount'] <= 0) {
                $issues[] = "Action {$index}: Invalid amount";
            }

            if (! in_array($action['action_type'] ?? '', ['buy', 'sell'])) {
                $issues[] = "Action {$index}: Invalid action type";
            }
        }

        // Check for warnings
        if (($plan['total_transaction_cost'] ?? 0) > ($plan['net_benefit'] ?? 0)) {
            $warnings[] = 'Transaction costs exceed expected net benefit';
        }

        if (count($plan['actions'] ?? []) > 10) {
            $warnings[] = 'Large number of actions may increase execution complexity';
        }

        $complexityScore = $this->calculateComplexityScore($plan);
        if ($complexityScore >= 8) {
            $warnings[] = 'High complexity plan may require additional oversight';
        }

        return [
            'is_valid'     => empty($issues),
            'issues'       => $issues,
            'warnings'     => $warnings,
            'validated_at' => now()->toISOString(),
        ];
    }
}
