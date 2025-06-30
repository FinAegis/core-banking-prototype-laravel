<?php

namespace App\Domain\Payment\Activities;

use App\Domain\Payment\DataObjects\BankWithdrawal;
use App\Domain\Banking\Events\WithdrawalRequested;
use Workflow\Activity;

class PublishWithdrawalRequestedActivity extends Activity
{
    public function execute(string $transactionId, string $transferId, BankWithdrawal $withdrawal): void
    {
        event(new WithdrawalRequested(
            accountUuid: $withdrawal->getAccountUuid(),
            transactionId: $transactionId,
            transferId: $transferId,
            amount: $withdrawal->getAmount(),
            currency: $withdrawal->getCurrency(),
            reference: $withdrawal->getReference(),
            metadata: array_merge($withdrawal->getMetadata(), [
                'bank_name' => $withdrawal->getBankName(),
                'transfer_id' => $transferId,
            ])
        ));
    }
}