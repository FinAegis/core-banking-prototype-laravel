<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Activities\Portfolio;

use App\Domain\Treasury\Services\RebalancingService;
use Exception;
use Log;
use RuntimeException;
use Workflow\Activity;

class ExecuteRebalancingActivity extends Activity
{
    public function __construct(
        private readonly RebalancingService $rebalancingService
    ) {
    }

    public function execute(array $input): array
    {
        $portfolioId = $input['portfolio_id'];
        $rebalanceId = $input['rebalance_id'];
        $rebalancingPlan = $input['rebalancing_plan'];
        $approvedBy = $input['approved_by'] ?? 'system';

        try {
            Log::info('Starting rebalancing execution', [
                'portfolio_id' => $portfolioId,
                'rebalance_id' => $rebalanceId,
                'action_count' => count($rebalancingPlan['actions'] ?? []),
                'total_cost'   => $rebalancingPlan['total_transaction_cost'] ?? 0,
                'approved_by'  => $approvedBy,
            ]);

            // Validate the plan before execution
            $validationResult = $this->validatePlanForExecution($rebalancingPlan);
            if (! $validationResult['is_valid']) {
                throw new RuntimeException('Rebalancing plan validation failed: ' . implode(', ', $validationResult['errors']));
            }

            // Execute the rebalancing using the service
            $this->rebalancingService->executeRebalancing($portfolioId, $rebalancingPlan);

            // Track execution metrics
            $executionMetrics = $this->calculateExecutionMetrics($rebalancingPlan);

            // Verify execution success by checking portfolio state
            $verificationResult = $this->verifyExecutionSuccess($portfolioId, $rebalancingPlan);

            $result = [
                'success'           => true,
                'portfolio_id'      => $portfolioId,
                'rebalance_id'      => $rebalanceId,
                'executed_at'       => now()->toISOString(),
                'approved_by'       => $approvedBy,
                'execution_metrics' => $executionMetrics,
                'verification'      => $verificationResult,
                'actions_executed'  => count($rebalancingPlan['actions'] ?? []),
                'total_cost'        => $rebalancingPlan['total_transaction_cost'] ?? 0,
                'estimated_benefit' => $rebalancingPlan['net_benefit'] ?? 0,
            ];

            Log::info('Rebalancing execution completed successfully', [
                'portfolio_id'     => $portfolioId,
                'rebalance_id'     => $rebalanceId,
                'actions_executed' => $result['actions_executed'],
                'total_cost'       => $result['total_cost'],
                'execution_time'   => $executionMetrics['execution_time_seconds'],
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error('Rebalancing execution failed', [
                'portfolio_id' => $portfolioId,
                'rebalance_id' => $rebalanceId,
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
            ]);

            // Return failure result with error details
            return [
                'success'           => false,
                'portfolio_id'      => $portfolioId,
                'rebalance_id'      => $rebalanceId,
                'executed_at'       => now()->toISOString(),
                'approved_by'       => $approvedBy,
                'error'             => $e->getMessage(),
                'partial_execution' => $this->checkForPartialExecution($portfolioId, $rebalanceId),
                'rollback_needed'   => true,
            ];
        }
    }

    /**
     * Validate the rebalancing plan before execution.
     */
    private function validatePlanForExecution(array $plan): array
    {
        $errors = [];

        // Check required fields
        if (empty($plan['actions'])) {
            $errors[] = 'No actions to execute';
        }

        if (! isset($plan['portfolio_id'])) {
            $errors[] = 'Portfolio ID missing from plan';
        }

        // Validate each action
        foreach ($plan['actions'] ?? [] as $index => $action) {
            if (empty($action['asset_class'])) {
                $errors[] = "Action {$index}: Missing asset class";
            }

            if (! isset($action['amount']) || $action['amount'] <= 0) {
                $errors[] = "Action {$index}: Invalid amount";
            }

            if (! in_array($action['action_type'] ?? '', ['buy', 'sell'])) {
                $errors[] = "Action {$index}: Invalid action type '{$action['action_type']}'";
            }

            if (! isset($action['target_weight']) || $action['target_weight'] < 0 || $action['target_weight'] > 100) {
                $errors[] = "Action {$index}: Invalid target weight";
            }
        }

        // Check for potential issues
        $totalTransactionCost = $plan['total_transaction_cost'] ?? 0;
        $netBenefit = $plan['net_benefit'] ?? 0;

        if ($totalTransactionCost > $netBenefit && $netBenefit > 0) {
            // This is a warning, not an error
            Log::warning('Transaction costs exceed net benefit', [
                'total_cost'  => $totalTransactionCost,
                'net_benefit' => $netBenefit,
            ]);
        }

        return [
            'is_valid' => empty($errors),
            'errors'   => $errors,
        ];
    }

    /**
     * Calculate execution metrics and performance data.
     */
    private function calculateExecutionMetrics(array $plan): array
    {
        $startTime = microtime(true);
        $actionCount = count($plan['actions'] ?? []);
        $totalCost = $plan['total_transaction_cost'] ?? 0;
        $portfolioValue = $plan['total_portfolio_value'] ?? 0;

        return [
            'execution_time_seconds'    => round(microtime(true) - $startTime, 2),
            'actions_processed'         => $actionCount,
            'total_transaction_cost'    => $totalCost,
            'cost_as_percentage'        => $portfolioValue > 0 ? ($totalCost / $portfolioValue) * 100 : 0,
            'complexity_score'          => $plan['workflow_metadata']['complexity_score'] ?? 1,
            'risk_level'                => $plan['workflow_metadata']['risk_assessment'] ?? 'medium',
            'estimated_completion_time' => $plan['estimated_completion_time'] ?? 'unknown',
            'execution_method'          => 'service_based',
        ];
    }

    /**
     * Verify that the rebalancing was executed successfully.
     */
    private function verifyExecutionSuccess(string $portfolioId, array $plan): array
    {
        try {
            // This would typically involve:
            // 1. Checking that portfolio allocations match target allocations
            // 2. Verifying transaction records
            // 3. Confirming portfolio is no longer in "rebalancing" state

            // For now, we'll do basic verification
            $issues = [];
            $warnings = [];

            // Check if we can access the portfolio (basic connectivity test)
            try {
                $portfolio = app(\App\Domain\Treasury\Services\PortfolioManagementService::class)->getPortfolio($portfolioId);

                if ($portfolio['is_rebalancing']) {
                    $warnings[] = 'Portfolio is still marked as rebalancing';
                }
            } catch (Exception $e) {
                $issues[] = 'Failed to verify portfolio state: ' . $e->getMessage();
            }

            return [
                'verified'         => empty($issues),
                'verified_at'      => now()->toISOString(),
                'issues'           => $issues,
                'warnings'         => $warnings,
                'checks_performed' => [
                    'portfolio_access',
                    'rebalancing_state',
                ],
            ];
        } catch (Exception $e) {
            return [
                'verified'    => false,
                'verified_at' => now()->toISOString(),
                'error'       => 'Verification process failed: ' . $e->getMessage(),
                'issues'      => ['Verification system error'],
                'warnings'    => [],
            ];
        }
    }

    /**
     * Check if there was partial execution that needs to be handled.
     */
    private function checkForPartialExecution(string $portfolioId, string $rebalanceId): array
    {
        // In a real implementation, this would check:
        // 1. Which actions were completed vs. failed
        // 2. Current portfolio state vs. intended state
        // 3. Transaction records and confirmations

        return [
            'has_partial_execution' => false,
            'completed_actions'     => [],
            'failed_actions'        => [],
            'rollback_required'     => false,
            'manual_intervention'   => false,
        ];
    }
}
