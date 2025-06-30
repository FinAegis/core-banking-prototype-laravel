<?php

namespace App\Domain\Payment\Workflows;

use App\Domain\Payment\DataObjects\StripeDeposit;
use App\Domain\Payment\Workflow\Activities\InitiateDepositActivity;
use App\Domain\Payment\Workflow\Activities\CompleteDepositActivity;
use App\Domain\Payment\Workflow\Activities\FailDepositActivity;
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
        try {
            // Step 1: Initiate the deposit using event sourcing
            $depositResult = yield ActivityStub::make(
                InitiateDepositActivity::class,
                [
                    'account_uuid' => $deposit->getAccountUuid(),
                    'amount' => $deposit->getAmount(),
                    'currency' => $deposit->getCurrency(),
                    'reference' => $deposit->getReference(),
                    'external_reference' => $deposit->getExternalReference(),
                    'payment_method' => $deposit->getPaymentMethod(),
                    'payment_method_type' => $deposit->getPaymentMethodType(),
                    'metadata' => $deposit->getMetadata()
                ]
            );

            $depositUuid = $depositResult['deposit_uuid'];

            // Step 2: Credit the account balance
            yield ActivityStub::make(
                CreditAccountActivity::class,
                $deposit->getAccountUuid(),
                $deposit->getAmount(),
                $deposit->getCurrency()
            );

            // Step 3: Generate transaction ID
            $transactionId = 'txn_' . uniqid();

            // Step 4: Complete the deposit
            yield ActivityStub::make(
                CompleteDepositActivity::class,
                [
                    'deposit_uuid' => $depositUuid,
                    'transaction_id' => $transactionId
                ]
            );

            // Step 5: Publish deposit completed event
            yield ActivityStub::make(
                PublishDepositCompletedActivity::class,
                $transactionId,
                $deposit
            );

            return $transactionId;
        } catch (\Throwable $e) {
            // If we have initiated a deposit, mark it as failed
            if (isset($depositUuid)) {
                yield ActivityStub::make(
                    FailDepositActivity::class,
                    [
                        'deposit_uuid' => $depositUuid,
                        'reason' => $e->getMessage()
                    ]
                );
            }
            
            throw $e;
        }
    }
}