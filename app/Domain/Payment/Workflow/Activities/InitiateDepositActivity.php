<?php

namespace App\Domain\Payment\Workflow\Activities;

use App\Domain\Payment\Aggregates\PaymentDepositAggregate;
use App\Domain\Payment\DataObjects\StripeDeposit;
use Illuminate\Support\Str;
use Workflow\Activity;

class InitiateDepositActivity extends Activity
{
    public function execute(array $input): array
    {
        $depositUuid = Str::uuid()->toString();
        
        $deposit = new StripeDeposit(
            accountUuid: $input['account_uuid'],
            amount: $input['amount'],
            currency: $input['currency'],
            reference: $input['reference'],
            externalReference: $input['external_reference'],
            paymentMethod: $input['payment_method'],
            paymentMethodType: $input['payment_method_type'],
            metadata: $input['metadata'] ?? []
        );
        
        PaymentDepositAggregate::retrieve($depositUuid)
            ->initiateDeposit($deposit)
            ->persist();
        
        return [
            'deposit_uuid' => $depositUuid,
            'status' => 'initiated'
        ];
    }
}