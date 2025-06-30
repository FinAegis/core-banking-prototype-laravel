<?php

namespace App\Domain\Payment\Workflows;

use App\Domain\Payment\DataObjects\StripeDeposit;
use App\Domain\Payment\Activities\RecordStripeDepositActivity;
use App\Domain\Payment\Activities\CreditAccountActivity;
use App\Domain\Payment\Activities\PublishDepositCompletedActivity;
use Workflow\ActivityStub;
use Workflow\Workflow;

class ProcessStripeDepositWorkflow extends Workflow
{
    /**
     * Process a Stripe deposit through the complete workflow
     * 
     * @param StripeDeposit $deposit
     * @return \Generator
     */
    public function execute(StripeDeposit $deposit): \Generator
    {
        // Step 1: Record the deposit transaction
        $transactionId = yield ActivityStub::make(
            RecordStripeDepositActivity::class,
            $deposit
        );

        // Step 2: Credit the account balance
        yield ActivityStub::make(
            CreditAccountActivity::class,
            $deposit->getAccountUuid(),
            $deposit->getAmount(),
            $deposit->getCurrency()
        );

        // Step 3: Publish deposit completed event
        yield ActivityStub::make(
            PublishDepositCompletedActivity::class,
            $transactionId,
            $deposit
        );

        return $transactionId;
    }
}