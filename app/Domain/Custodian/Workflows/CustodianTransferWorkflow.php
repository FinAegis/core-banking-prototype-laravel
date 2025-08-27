<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Workflows;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Workflows\DepositAccountWorkflow;
use App\Domain\Account\Workflows\WithdrawAccountWorkflow;
use App\Domain\Custodian\Workflows\Activities\InitiateCustodianTransferActivity;
use App\Domain\Custodian\Workflows\Activities\VerifyCustodianTransferActivity;
use Generator;
use Throwable;
use Workflow\ActivityStub;
use Workflow\ChildWorkflowStub;
use Workflow\Workflow;

class CustodianTransferWorkflow extends Workflow
{
    /**
     * Transfer funds between internal account and custodian account.
     */
    public function execute(
        AccountUuid $internalAccount,
        string $custodianAccount,
        string $assetCode,
        Money $amount,
        string $custodianName,
        string $direction = 'deposit', // 'deposit' or 'withdraw'
        ?string $reference = null
    ): Generator {
        try {
            if ($direction === 'deposit') {
                // Deposit from custodian to internal account

                // First, initiate transfer from custodian
                $transactionId = yield ActivityStub::make(
                    InitiateCustodianTransferActivity::class,
                    $custodianAccount,
                    $internalAccount->getUuid(),
                    $assetCode,
                    $amount,
                    $custodianName,
                    'incoming',
                    $reference
                );

                // Add compensation to reverse custodian transfer if needed
                $this->addCompensation(
                    fn () => ActivityStub::make(
                        InitiateCustodianTransferActivity::class,
                        $internalAccount->getUuid(),
                        $custodianAccount,
                        $assetCode,
                        $amount,
                        $custodianName,
                        'outgoing',
                        "Reversal of {$transactionId}"
                    )
                );

                // Verify transfer completed
                yield ActivityStub::make(
                    VerifyCustodianTransferActivity::class,
                    $transactionId,
                    $custodianName
                );

                // Credit internal account
                yield ChildWorkflowStub::make(
                    DepositAccountWorkflow::class,
                    $internalAccount,
                    $amount
                );
            } else {
                // Withdraw from internal account to custodian

                // First, debit internal account
                yield ChildWorkflowStub::make(
                    WithdrawAccountWorkflow::class,
                    $internalAccount,
                    $amount
                );

                // Add compensation to restore internal balance
                $this->addCompensation(
                    fn () => ChildWorkflowStub::make(
                        DepositAccountWorkflow::class,
                        $internalAccount,
                        $amount
                    )
                );

                // Initiate transfer to custodian
                $transactionId = yield ActivityStub::make(
                    InitiateCustodianTransferActivity::class,
                    $internalAccount->getUuid(),
                    $custodianAccount,
                    $assetCode,
                    $amount,
                    $custodianName,
                    'outgoing',
                    $reference
                );

                // Verify transfer completed
                yield ActivityStub::make(
                    VerifyCustodianTransferActivity::class,
                    $transactionId,
                    $custodianName
                );
            }

            return [
                'status'         => 'completed',
                'transaction_id' => $transactionId,
                'direction'      => $direction,
                'amount'         => $amount->getAmount(),
                'asset_code'     => $assetCode,
            ];
        } catch (Throwable $e) {
            // Execute compensations
            yield from $this->compensate();

            throw $e;
        }
    }
}
