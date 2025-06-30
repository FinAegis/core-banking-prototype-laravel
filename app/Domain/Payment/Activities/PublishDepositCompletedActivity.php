<?php

namespace App\Domain\Payment\Activities;

use App\Domain\Payment\DataObjects\StripeDeposit;
use App\Domain\Banking\Events\DepositCompleted;
use Workflow\Activity;

class PublishDepositCompletedActivity extends Activity
{
    public function execute(string $transactionId, StripeDeposit $deposit): void
    {
        event(new DepositCompleted(
            accountUuid: $deposit->getAccountUuid(),
            transactionId: $transactionId,
            amount: $deposit->getAmount(),
            currency: $deposit->getCurrency(),
            reference: $deposit->getReference(),
            metadata: array_merge($deposit->getMetadata(), [
                'payment_method' => $deposit->getPaymentMethod(),
                'payment_method_type' => $deposit->getPaymentMethodType(),
            ])
        ));
    }
}