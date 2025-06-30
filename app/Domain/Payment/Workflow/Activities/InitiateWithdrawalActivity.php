<?php

namespace App\Domain\Payment\Workflow\Activities;

use App\Domain\Payment\Aggregates\PaymentWithdrawalAggregate;
use App\Domain\Payment\DataObjects\BankWithdrawal;
use Illuminate\Support\Str;
use Workflow\Activity;

class InitiateWithdrawalActivity extends Activity
{
    public function execute(array $input): array
    {
        $withdrawalUuid = Str::uuid()->toString();
        
        $withdrawal = new BankWithdrawal(
            accountUuid: $input['account_uuid'],
            amount: $input['amount'],
            currency: $input['currency'],
            reference: $input['reference'],
            bankAccountNumber: $input['bank_account_number'],
            bankRoutingNumber: $input['bank_routing_number'],
            bankAccountName: $input['bank_account_name'],
            metadata: $input['metadata'] ?? []
        );
        
        PaymentWithdrawalAggregate::retrieve($withdrawalUuid)
            ->initiateWithdrawal($withdrawal)
            ->persist();
        
        return [
            'withdrawal_uuid' => $withdrawalUuid,
            'status' => 'initiated'
        ];
    }
}