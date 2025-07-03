<?php

namespace App\Domain\Cgo\Activities;

use App\Domain\Cgo\Aggregates\RefundAggregate;
use Illuminate\Support\Str;
use Workflow\Activity;

class InitiateRefundActivity extends Activity
{
    public function execute(array $input): array
    {
        $refundId = Str::uuid()->toString();
        
        RefundAggregate::retrieve($refundId)
            ->requestRefund(
                refundId: $refundId,
                investmentId: $input['investment_id'],
                userId: $input['user_id'],
                amount: $input['amount'],
                currency: $input['currency'],
                reason: $input['reason'],
                reasonDetails: $input['reason_details'] ?? null,
                initiatedBy: $input['initiated_by'],
                metadata: $input['metadata'] ?? []
            )
            ->persist();
        
        return [
            'refund_id' => $refundId,
            'status' => 'initiated'
        ];
    }
}