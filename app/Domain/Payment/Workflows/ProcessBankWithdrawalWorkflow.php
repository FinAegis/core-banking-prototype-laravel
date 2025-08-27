<?php

namespace App\Domain\Payment\Workflows;

use App\Domain\Payment\Activities\DebitAccountActivity;
use App\Domain\Payment\Activities\InitiateBankTransferActivity;
use App\Domain\Payment\Activities\PublishWithdrawalRequestedActivity;
use App\Domain\Payment\Activities\ValidateWithdrawalActivity;
use App\Domain\Payment\DataObjects\BankWithdrawal;
use App\Domain\Payment\Workflow\Activities\CompleteWithdrawalActivity;
use App\Domain\Payment\Workflow\Activities\FailWithdrawalActivity;
use App\Domain\Payment\Workflow\Activities\InitiateWithdrawalActivity;
use Exception;
use Generator;
use Throwable;
use Workflow\ActivityStub;
use Workflow\Workflow;

class ProcessBankWithdrawalWorkflow extends Workflow
{
    /**
     * Process a bank withdrawal through the complete workflow.
     */
    public function execute(BankWithdrawal $withdrawal): Generator
    {
        $withdrawalUuid = null;

        try {
            // Step 1: Validate withdrawal (check balance, limits, etc.)
            $validation = yield ActivityStub::make(
                ValidateWithdrawalActivity::class,
                $withdrawal
            );

            if (! $validation['valid']) {
                throw new Exception($validation['message'] ?? 'Withdrawal validation failed');
            }

            // Step 2: Initiate the withdrawal using event sourcing
            $withdrawalResult = yield ActivityStub::make(
                InitiateWithdrawalActivity::class,
                [
                    'account_uuid'        => $withdrawal->getAccountUuid(),
                    'amount'              => $withdrawal->getAmount(),
                    'currency'            => $withdrawal->getCurrency(),
                    'reference'           => $withdrawal->getReference(),
                    'bank_account_number' => $withdrawal->getAccountNumber(),
                    'bank_routing_number' => $withdrawal->getRoutingNumber(),
                    'bank_account_name'   => $withdrawal->getAccountHolderName(),
                    'metadata'            => $withdrawal->getMetadata(),
                ]
            );

            $withdrawalUuid = $withdrawalResult['withdrawal_uuid'];

            // Step 3: Debit the account balance (hold funds)
            yield ActivityStub::make(
                DebitAccountActivity::class,
                $withdrawal->getAccountUuid(),
                $withdrawal->getAmount(),
                $withdrawal->getCurrency()
            );

            // Step 4: Generate transaction ID
            $transactionId = 'wtxn_' . uniqid();

            // Step 5: Initiate bank transfer (could be async)
            $transferId = yield ActivityStub::make(
                InitiateBankTransferActivity::class,
                $transactionId,
                $withdrawal
            );

            // Step 6: Complete the withdrawal
            yield ActivityStub::make(
                CompleteWithdrawalActivity::class,
                [
                    'withdrawal_uuid' => $withdrawalUuid,
                    'transaction_id'  => $transactionId,
                ]
            );

            // Step 7: Publish withdrawal requested event
            yield ActivityStub::make(
                PublishWithdrawalRequestedActivity::class,
                $transactionId,
                $transferId,
                $withdrawal
            );

            return [
                'transaction_id' => $transactionId,
                'transfer_id'    => $transferId,
                'reference'      => $withdrawal->getReference(),
            ];
        } catch (Throwable $e) {
            // If we have initiated a withdrawal, mark it as failed
            if ($withdrawalUuid !== null) {
                yield ActivityStub::make(
                    FailWithdrawalActivity::class,
                    [
                        'withdrawal_uuid' => $withdrawalUuid,
                        'reason'          => $e->getMessage(),
                    ]
                );
            }

            throw $e;
        }
    }
}
