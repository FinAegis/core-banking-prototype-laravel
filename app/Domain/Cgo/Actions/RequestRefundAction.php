<?php

namespace App\Domain\Cgo\Actions;

use App\Domain\Cgo\DataObjects\RefundRequest;
use App\Domain\Cgo\Workflows\ProcessRefundWorkflow;
use App\Models\CgoInvestment;
use App\Models\User;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;

class RequestRefundAction
{
    public function __construct(
        private WorkflowClient $workflowClient
    ) {}
    
    public function execute(
        CgoInvestment $investment,
        User $initiator,
        string $reason,
        ?string $reasonDetails = null,
        array $metadata = []
    ): array {
        // Check if investment can be refunded
        if (!$investment->canBeRefunded()) {
            throw new \DomainException('This investment cannot be refunded');
        }
        
        // Check for existing pending refunds
        if ($investment->refunds()->whereIn('status', ['pending', 'approved', 'processing'])->exists()) {
            throw new \DomainException('A refund is already in progress for this investment');
        }
        
        // Determine if auto-approval is allowed
        $autoApproved = $this->shouldAutoApprove($investment, $reason);
        
        // Create refund request
        $refundRequest = new RefundRequest(
            investmentId: $investment->id,
            userId: $investment->user_id,
            amount: $investment->amount,
            currency: $investment->currency,
            reason: $reason,
            reasonDetails: $reasonDetails,
            initiatedBy: $initiator->id,
            autoApproved: $autoApproved,
            metadata: array_merge($metadata, [
                'investment_uuid' => $investment->uuid,
                'investment_package' => $investment->package,
                'payment_method' => $investment->payment_method,
            ])
        );
        
        // Start the refund workflow
        $workflow = $this->workflowClient->newWorkflow(
            ProcessRefundWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowId('refund_' . $investment->uuid . '_' . uniqid())
                ->withTaskQueue('cgo-refunds')
        );
        
        $run = $this->workflowClient->start($workflow, $refundRequest);
        
        return [
            'workflow_id' => $run->getExecution()->getID(),
            'status' => 'initiated',
            'auto_approved' => $autoApproved,
        ];
    }
    
    private function shouldAutoApprove(CgoInvestment $investment, string $reason): bool
    {
        // Auto-approve small amounts or specific reasons
        if ($investment->amount <= 10000) { // $100 or less
            return true;
        }
        
        // Auto-approve if within grace period (e.g., 7 days)
        if ($investment->payment_completed_at && $investment->payment_completed_at->diffInDays(now()) <= 7) {
            return true;
        }
        
        // Auto-approve specific reasons
        $autoApproveReasons = ['duplicate_payment', 'payment_error', 'system_error'];
        if (in_array($reason, $autoApproveReasons)) {
            return true;
        }
        
        return false;
    }
}