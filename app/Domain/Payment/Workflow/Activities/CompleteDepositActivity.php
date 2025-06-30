<?php

namespace App\Domain\Payment\Workflow\Activities;

use App\Domain\Payment\Aggregates\PaymentDepositAggregate;
use Workflow\Activity;

class CompleteDepositActivity extends Activity
{
    public function execute(array $input): array
    {
        PaymentDepositAggregate::retrieve($input['deposit_uuid'])
            ->completeDeposit($input['transaction_id'])
            ->persist();
        
        return [
            'deposit_uuid' => $input['deposit_uuid'],
            'status' => 'completed',
            'transaction_id' => $input['transaction_id']
        ];
    }
}