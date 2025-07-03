<?php

namespace App\Domain\Cgo\Workflows;

use App\Domain\Cgo\Activities\ApproveRefundActivity;
use App\Domain\Cgo\Activities\InitiateRefundActivity;
use App\Domain\Cgo\Activities\ProcessRefundActivity;
use App\Domain\Cgo\Activities\CompleteRefundActivity;
use App\Domain\Cgo\Activities\FailRefundActivity;
use App\Domain\Cgo\DataObjects\RefundRequest;
use Workflow\ActivityStub;
use Workflow\Workflow;

class ProcessRefundWorkflow extends Workflow
{
    /**
     * Process a CGO refund through the complete workflow
     * 
     * @param RefundRequest $request
     * @return \Generator
     */
    public function execute(RefundRequest $request): \Generator
    {
        $refundId = null;
        
        try {
            // Step 1: Initiate the refund request
            $initiateResult = yield ActivityStub::make(
                InitiateRefundActivity::class,
                [
                    'investment_id' => $request->getInvestmentId(),
                    'user_id' => $request->getUserId(),
                    'amount' => $request->getAmount(),
                    'currency' => $request->getCurrency(),
                    'reason' => $request->getReason(),
                    'reason_details' => $request->getReasonDetails(),
                    'initiated_by' => $request->getInitiatedBy(),
                    'metadata' => $request->getMetadata()
                ]
            );
            
            $refundId = $initiateResult['refund_id'];
            
            // Step 2: Approve the refund (in production, this might wait for manual approval)
            if ($request->isAutoApproved()) {
                yield ActivityStub::make(
                    ApproveRefundActivity::class,
                    [
                        'refund_id' => $refundId,
                        'approved_by' => $request->getInitiatedBy(),
                        'approval_notes' => 'Auto-approved based on policy'
                    ]
                );
            } else {
                // In production, this would wait for manual approval
                // For now, we'll simulate a wait
                yield Workflow::await(fn() => false);
                return ['status' => 'pending_approval', 'refund_id' => $refundId];
            }
            
            // Step 3: Process the refund with the payment processor
            $processResult = yield ActivityStub::make(
                ProcessRefundActivity::class,
                [
                    'refund_id' => $refundId
                ]
            );
            
            // Step 4: Complete the refund
            yield ActivityStub::make(
                CompleteRefundActivity::class,
                [
                    'refund_id' => $refundId,
                    'processor_refund_id' => $processResult['processor_refund_id'],
                    'amount_refunded' => $processResult['amount_refunded']
                ]
            );
            
            return [
                'status' => 'completed',
                'refund_id' => $refundId,
                'processor_refund_id' => $processResult['processor_refund_id'],
                'amount_refunded' => $processResult['amount_refunded']
            ];
            
        } catch (\Throwable $e) {
            // If we have initiated a refund, mark it as failed
            if ($refundId !== null) {
                yield ActivityStub::make(
                    FailRefundActivity::class,
                    [
                        'refund_id' => $refundId,
                        'reason' => $e->getMessage()
                    ]
                );
            }
            
            throw $e;
        }
    }
}