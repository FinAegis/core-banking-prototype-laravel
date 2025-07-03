<?php

namespace App\Domain\Cgo\Activities;

use App\Domain\Cgo\Aggregates\RefundAggregate;
use Workflow\Activity;

class FailRefundActivity extends Activity
{
    public function execute(array $input): array
    {
        RefundAggregate::retrieve($input['refund_id'])
            ->fail(
                failureReason: $input['reason'],
                failedAt: now()->toIso8601String(),
                metadata: $input['metadata'] ?? []
            )
            ->persist();
        
        return [
            'refund_id' => $input['refund_id'],
            'status' => 'failed',
            'reason' => $input['reason']
        ];
    }
}