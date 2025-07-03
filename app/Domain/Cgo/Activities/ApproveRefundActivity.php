<?php

namespace App\Domain\Cgo\Activities;

use App\Domain\Cgo\Aggregates\RefundAggregate;
use Workflow\Activity;

class ApproveRefundActivity extends Activity
{
    public function execute(array $input): array
    {
        RefundAggregate::retrieve($input['refund_id'])
            ->approve(
                approvedBy: $input['approved_by'],
                approvalNotes: $input['approval_notes'],
                metadata: $input['metadata'] ?? []
            )
            ->persist();
        
        return [
            'refund_id' => $input['refund_id'],
            'status' => 'approved'
        ];
    }
}