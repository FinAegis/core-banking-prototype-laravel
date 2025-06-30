<?php

namespace App\Domain\Payment\Workflow\Activities;

use App\Domain\Payment\Aggregates\PaymentWithdrawalAggregate;
use Workflow\Activity;

class CompleteWithdrawalActivity extends Activity
{
    public function execute(array $input): array
    {
        PaymentWithdrawalAggregate::retrieve($input['withdrawal_uuid'])
            ->completeWithdrawal($input['transaction_id'])
            ->persist();
        
        return [
            'withdrawal_uuid' => $input['withdrawal_uuid'],
            'status' => 'completed',
            'transaction_id' => $input['transaction_id']
        ];
    }
}