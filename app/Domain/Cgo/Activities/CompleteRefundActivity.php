<?php

namespace App\Domain\Cgo\Activities;

use App\Domain\Cgo\Aggregates\RefundAggregate;
use Workflow\Activity;

class CompleteRefundActivity extends Activity
{
    public function execute(array $input): array
    {
        RefundAggregate::retrieve($input['refund_id'])
            ->complete(
                completedAt: now()->toIso8601String(),
                metadata: [
                    'processor_refund_id' => $input['processor_refund_id'],
                    'amount_refunded' => $input['amount_refunded']
                ]
            )
            ->persist();
        
        return [
            'refund_id' => $input['refund_id'],
            'status' => 'completed'
        ];
    }
}