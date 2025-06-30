<?php

namespace App\Domain\Payment\Workflow\Activities;

use App\Domain\Payment\Aggregates\PaymentWithdrawalAggregate;
use Workflow\Activity;

class FailWithdrawalActivity extends Activity
{
    public function execute(array $input): array
    {
        PaymentWithdrawalAggregate::retrieve($input['withdrawal_uuid'])
            ->failWithdrawal($input['reason'])
            ->persist();
        
        return [
            'withdrawal_uuid' => $input['withdrawal_uuid'],
            'status' => 'failed',
            'reason' => $input['reason']
        ];
    }
}