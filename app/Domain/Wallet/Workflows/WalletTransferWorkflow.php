<?php

namespace App\Domain\Wallet\Workflows;

use App\Domain\Account\DataObjects\AccountUuid;
use Generator;
use Throwable;
use Workflow\ChildWorkflowStub;
use Workflow\Workflow;

class WalletTransferWorkflow extends Workflow
{
    /**
     * Execute wallet transfer between accounts for a specific asset
     * Uses compensation pattern for rollback safety.
     */
    public function execute(
        AccountUuid $fromAccountUuid,
        AccountUuid $toAccountUuid,
        string $assetCode,
        int $amount,
        ?string $reference = null
    ): Generator {
        try {
            // Step 1: Withdraw from source account
            yield ChildWorkflowStub::make(
                WalletWithdrawWorkflow::class,
                $fromAccountUuid,
                $assetCode,
                $amount
            );

            // Add compensation: if deposit fails, re-deposit to source account
            $this->addCompensation(
                fn () => ChildWorkflowStub::make(
                    WalletDepositWorkflow::class,
                    $fromAccountUuid,
                    $assetCode,
                    $amount
                )
            );

            // Step 2: Deposit to destination account
            yield ChildWorkflowStub::make(
                WalletDepositWorkflow::class,
                $toAccountUuid,
                $assetCode,
                $amount
            );

            // Add compensation: if needed later, withdraw from destination
            $this->addCompensation(
                fn () => ChildWorkflowStub::make(
                    WalletWithdrawWorkflow::class,
                    $toAccountUuid,
                    $assetCode,
                    $amount
                )
            );
        } catch (Throwable $th) {
            // Execute compensation workflows in reverse order
            yield from $this->compensate();
            throw $th;
        }
    }
}
