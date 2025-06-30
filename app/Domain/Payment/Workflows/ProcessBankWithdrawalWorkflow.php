<?php

namespace App\Domain\Payment\Workflows;

use App\Domain\Payment\DataObjects\BankWithdrawal;
use App\Domain\Payment\Activities\ValidateWithdrawalActivity;
use App\Domain\Payment\Activities\RecordWithdrawalActivity;
use App\Domain\Payment\Activities\DebitAccountActivity;
use App\Domain\Payment\Activities\InitiateBankTransferActivity;
use App\Domain\Payment\Activities\PublishWithdrawalRequestedActivity;
use Workflow\ActivityStub;
use Workflow\Workflow;

class ProcessBankWithdrawalWorkflow extends Workflow
{
    /**
     * Process a bank withdrawal through the complete workflow
     * 
     * @param BankWithdrawal $withdrawal
     * @return \Generator
     */
    public function execute(BankWithdrawal $withdrawal): \Generator
    {
        // Step 1: Validate withdrawal (check balance, limits, etc.)
        $validation = yield ActivityStub::make(
            ValidateWithdrawalActivity::class,
            $withdrawal
        );

        if (!$validation['valid']) {
            throw new \Exception($validation['message'] ?? 'Withdrawal validation failed');
        }

        // Step 2: Record the withdrawal transaction (status: pending)
        $transactionId = yield ActivityStub::make(
            RecordWithdrawalActivity::class,
            $withdrawal
        );

        // Step 3: Debit the account balance (hold funds)
        yield ActivityStub::make(
            DebitAccountActivity::class,
            $withdrawal->getAccountUuid(),
            $withdrawal->getAmount(),
            $withdrawal->getCurrency()
        );

        // Step 4: Initiate bank transfer (could be async)
        $transferId = yield ActivityStub::make(
            InitiateBankTransferActivity::class,
            $transactionId,
            $withdrawal
        );

        // Step 5: Publish withdrawal requested event
        yield ActivityStub::make(
            PublishWithdrawalRequestedActivity::class,
            $transactionId,
            $transferId,
            $withdrawal
        );

        return [
            'transaction_id' => $transactionId,
            'transfer_id' => $transferId,
            'reference' => $withdrawal->getReference(),
        ];
    }
}