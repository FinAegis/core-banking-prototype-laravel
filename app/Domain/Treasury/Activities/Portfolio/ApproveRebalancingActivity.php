<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Activities\Portfolio;

use App\Domain\Treasury\Events\Portfolio\RebalancingApprovalRequested;
use Exception;
use Log;
use Workflow\Activity\ActivityInterface;
use Workflow\Activity\ActivityMethod;

#[ActivityInterface]
class ApproveRebalancingActivity
{
    #[ActivityMethod]
    public function execute(array $input): array
    {
        $portfolioId = $input['portfolio_id'];
        $rebalanceId = $input['rebalance_id'];
        $rebalancingPlan = $input['rebalancing_plan'];
        $reason = $input['reason'] ?? 'scheduled_rebalancing';

        // Generate unique approval ID
        $approvalId = uniqid('approval_' . $portfolioId . '_', true);

        try {
            // Fire approval request event
            event(new RebalancingApprovalRequested(
                portfolioId: $portfolioId,
                rebalanceId: $rebalanceId,
                rebalancingPlan: $rebalancingPlan,
                reason: $reason,
                approvalId: $approvalId,
                metadata: [
                    'workflow_id'  => $input['workflow_id'] ?? null,
                    'requested_at' => now()->toISOString(),
                ],
                requiredApprovers: []
            ));

            // For automated approval in activities, check if it meets auto-approval criteria
            $autoApprove = $this->shouldAutoApprove($rebalancingPlan);

            if ($autoApprove) {
                return [
                    'approved'          => true,
                    'approval_id'       => $approvalId,
                    'approver_id'       => 'system_auto',
                    'comments'          => 'Auto-approved: meets all criteria',
                    'timed_out'         => false,
                    'rejection_reason'  => null,
                    'approval_metadata' => [
                        'portfolio_id'     => $portfolioId,
                        'rebalance_id'     => $rebalanceId,
                        'requested_at'     => now()->toISOString(),
                        'completed_at'     => now()->toISOString(),
                        'total_actions'    => count($rebalancingPlan['actions'] ?? []),
                        'total_cost'       => $rebalancingPlan['total_transaction_cost'] ?? 0,
                        'complexity_score' => $rebalancingPlan['workflow_metadata']['complexity_score'] ?? 1,
                    ],
                ];
            }

            // For manual approval, would need to check an external system or queue
            // For now, we'll simulate a manual approval requirement
            return [
                'approved'          => false,
                'approval_id'       => $approvalId,
                'approver_id'       => 'manual_required',
                'comments'          => 'Manual approval required for this rebalancing plan',
                'timed_out'         => false,
                'rejection_reason'  => 'Pending manual approval',
                'approval_metadata' => [
                    'portfolio_id'     => $portfolioId,
                    'rebalance_id'     => $rebalanceId,
                    'requested_at'     => now()->toISOString(),
                    'total_actions'    => count($rebalancingPlan['actions'] ?? []),
                    'total_cost'       => $rebalancingPlan['total_transaction_cost'] ?? 0,
                    'complexity_score' => $rebalancingPlan['workflow_metadata']['complexity_score'] ?? 1,
                ],
            ];
        } catch (Exception $e) {
            Log::error('Approval process failed', [
                'portfolio_id' => $portfolioId,
                'rebalance_id' => $rebalanceId,
                'approval_id'  => $approvalId,
                'error'        => $e->getMessage(),
            ]);

            return [
                'approved'         => false,
                'approval_id'      => $approvalId,
                'approver_id'      => 'system_error',
                'comments'         => 'Approval process failed: ' . $e->getMessage(),
                'timed_out'        => false,
                'rejection_reason' => 'System error during approval',
                'error'            => true,
            ];
        }
    }

    /**
     * Determine if the rebalancing plan should be auto-approved.
     */
    private function shouldAutoApprove(array $rebalancingPlan): bool
    {
        // Auto-approve criteria
        $maxTransactionCost = 10000; // $10,000
        $maxComplexity = 5;
        $maxActions = 10;

        $totalCost = $rebalancingPlan['total_transaction_cost'] ?? 0;
        $complexity = $rebalancingPlan['workflow_metadata']['complexity_score'] ?? 10;
        $actionCount = count($rebalancingPlan['actions'] ?? []);

        return $totalCost <= $maxTransactionCost
            && $complexity <= $maxComplexity
            && $actionCount <= $maxActions;
    }
}
