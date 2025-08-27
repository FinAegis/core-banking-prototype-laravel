<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Workflows;

use App\Domain\Treasury\Activities\Portfolio\ApproveRebalancingActivity;
use App\Domain\Treasury\Activities\Portfolio\CalculateRebalancingPlanActivity;
use App\Domain\Treasury\Activities\Portfolio\CheckRebalancingNeedActivity;
use App\Domain\Treasury\Activities\Portfolio\ExecuteRebalancingActivity;
use App\Domain\Treasury\Activities\Portfolio\NotifyRebalancingCompleteActivity;
use App\Domain\Treasury\Aggregates\PortfolioAggregate;
use Exception;
use Illuminate\Support\Str;
use Log;
use RuntimeException;
use Workflow\Activity;
use Workflow\Workflow;

class PortfolioRebalancingWorkflow extends Workflow
{
    private string $portfolioId;

    private string $rebalanceId;

    private array $rebalancingPlan = [];

    private bool $approved = false;

    private string $reason;

    public function execute(
        string $portfolioId,
        string $reason = 'scheduled_rebalancing',
        array $overrides = []
    ) {
        $this->portfolioId = $portfolioId;
        $this->reason = $reason;
        $this->rebalanceId = Str::uuid()->toString();

        try {
            // Step 1: Check if rebalancing is needed
            $needsRebalancing = yield Activity::make(CheckRebalancingNeedActivity::class, [
                'portfolio_id' => $this->portfolioId,
                'reason'       => $this->reason,
                'overrides'    => $overrides,
            ]);

            if (! $needsRebalancing['needed']) {
                return [
                    'success'         => true,
                    'rebalance_id'    => $this->rebalanceId,
                    'action_taken'    => 'none',
                    'reason'          => $needsRebalancing['reason'],
                    'portfolio_id'    => $this->portfolioId,
                    'needs_attention' => false,
                ];
            }

            // Step 2: Calculate detailed rebalancing plan
            $this->rebalancingPlan = yield Activity::make(CalculateRebalancingPlanActivity::class, [
                'portfolio_id'   => $this->portfolioId,
                'rebalance_id'   => $this->rebalanceId,
                'reason'         => $this->reason,
                'drift_analysis' => $needsRebalancing['drift_analysis'],
                'overrides'      => $overrides,
            ]);

            // Step 3: Human approval for significant rebalancing
            if ($this->requiresHumanApproval($this->rebalancingPlan)) {
                $approval = yield Activity::make(ApproveRebalancingActivity::class, [
                    'portfolio_id'     => $this->portfolioId,
                    'rebalance_id'     => $this->rebalanceId,
                    'rebalancing_plan' => $this->rebalancingPlan,
                    'reason'           => $this->reason,
                    'timeout'          => 3600, // 1 hour timeout for approval
                ]);

                $this->approved = $approval['approved'];

                if (! $this->approved) {
                    return [
                        'success'      => false,
                        'rebalance_id' => $this->rebalanceId,
                        'action_taken' => 'rejected',
                        'reason'       => $approval['rejection_reason'] ?? 'Manual rejection',
                        'portfolio_id' => $this->portfolioId,
                        'approver'     => $approval['approver_id'] ?? 'timeout',
                    ];
                }
            } else {
                $this->approved = true; // Auto-approve minor rebalancing
            }

            // Step 4: Execute the rebalancing plan
            $execution = yield Activity::make(ExecuteRebalancingActivity::class, [
                'portfolio_id'     => $this->portfolioId,
                'rebalance_id'     => $this->rebalanceId,
                'rebalancing_plan' => $this->rebalancingPlan,
                'approved_by'      => $this->requiresHumanApproval($this->rebalancingPlan) ? 'manual' : 'system',
            ]);

            // Step 5: Notify stakeholders of completion
            yield Activity::make(NotifyRebalancingCompleteActivity::class, [
                'portfolio_id'      => $this->portfolioId,
                'rebalance_id'      => $this->rebalanceId,
                'execution_results' => $execution,
                'original_plan'     => $this->rebalancingPlan,
            ]);

            return [
                'success'           => true,
                'rebalance_id'      => $this->rebalanceId,
                'action_taken'      => 'completed',
                'portfolio_id'      => $this->portfolioId,
                'execution_results' => $execution,
                'transaction_cost'  => $this->rebalancingPlan['total_transaction_cost'] ?? 0,
                'actions_executed'  => count($this->rebalancingPlan['actions'] ?? []),
                'approval_required' => $this->requiresHumanApproval($this->rebalancingPlan),
                'approved_by'       => $this->requiresHumanApproval($this->rebalancingPlan) ? 'manual' : 'system',
            ];
        } catch (Exception $e) {
            // Compensation: Rollback any partial changes
            yield from $this->compensate();

            throw new RuntimeException(
                "Portfolio rebalancing workflow failed for portfolio {$this->portfolioId}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    public function compensate()
    {
        if (! empty($this->rebalancingPlan) && ! empty($this->rebalanceId)) {
            // Mark rebalancing as failed in the portfolio aggregate
            $aggregate = PortfolioAggregate::retrieve($this->portfolioId);

            // If rebalancing was in progress, mark it as failed
            if ($aggregate->isRebalancing()) {
                // Note: This would require implementing a failRebalancing method in the aggregate
                // For now, we'll log the failure
                Log::warning('Rebalancing workflow failed, manual intervention may be required', [
                    'portfolio_id' => $this->portfolioId,
                    'rebalance_id' => $this->rebalanceId,
                    'plan'         => $this->rebalancingPlan,
                ]);
            }

            // Send failure notification
            yield Activity::make(NotifyRebalancingCompleteActivity::class, [
                'portfolio_id'      => $this->portfolioId,
                'rebalance_id'      => $this->rebalanceId,
                'execution_results' => [
                    'success' => false,
                    'error'   => 'Workflow compensation triggered',
                ],
                'original_plan' => $this->rebalancingPlan,
                'status'        => 'failed',
            ]);
        }
    }

    /**
     * Determine if rebalancing requires human approval based on plan characteristics.
     */
    private function requiresHumanApproval(array $plan): bool
    {
        // Large transaction amounts require approval
        $totalTransactionCost = $plan['total_transaction_cost'] ?? 0;
        if ($totalTransactionCost > 10000) { // $10,000 threshold
            return true;
        }

        // High-risk rebalancing requires approval
        $riskImpact = $plan['risk_impact'] ?? 'low_risk_reduction';
        if ($riskImpact === 'high_risk_reduction') {
            return true;
        }

        // Large number of actions requires approval
        $actionCount = count($plan['actions'] ?? []);
        if ($actionCount > 5) {
            return true;
        }

        // Large portfolio value changes require approval
        $totalPortfolioValue = $plan['total_portfolio_value'] ?? 0;
        $totalRebalanceValue = array_sum(array_column($plan['actions'] ?? [], 'amount'));
        $rebalancePercentage = $totalPortfolioValue > 0 ? ($totalRebalanceValue / $totalPortfolioValue) : 0;

        if ($rebalancePercentage > 0.2) { // 20% of portfolio value
            return true;
        }

        return false;
    }
}
