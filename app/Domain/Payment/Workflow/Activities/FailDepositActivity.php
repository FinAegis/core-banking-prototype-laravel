<?php

namespace App\Domain\Payment\Workflow\Activities;

use App\Domain\Payment\Aggregates\PaymentDepositAggregate;
use Workflow\Activity;

class FailDepositActivity extends Activity
{
    public function execute(array $input): array
    {
        PaymentDepositAggregate::retrieve($input['deposit_uuid'])
            ->failDeposit($input['reason'])
            ->persist();
        
        return [
            'deposit_uuid' => $input['deposit_uuid'],
            'status' => 'failed',
            'reason' => $input['reason']
        ];
    }
}