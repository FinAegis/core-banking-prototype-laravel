<?php

declare(strict_types=1);

namespace App\Domain\Payment\Workflows;

use App\Domain\Account\Models\Account;
use App\Domain\Payment\DataObjects\OpenBankingDeposit;
use App\Domain\Transaction\Aggregates\TransactionAggregate;
use App\Models\Transaction;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Log;
use Workflow\ActivityStub;
use Workflow\Workflow;

class ProcessOpenBankingDepositWorkflow extends Workflow
{
    private ActivityStub $activity;

    public function __construct()
    {
        $this->activity = ActivityStub::make(ProcessOpenBankingDepositActivity::class, CarbonInterval::minutes(5));
    }

    public function execute(OpenBankingDeposit $deposit): \Generator
    {
        Log::info('Starting OpenBanking deposit workflow', [
            'reference' => $deposit->reference,
            'amount'    => $deposit->amount,
            'bank'      => $deposit->bankName,
        ]);

        try {
            // Step 1: Validate account exists
            $account = yield $this->activity->validateAccount($deposit->accountUuid);

            // Step 2: Create transaction aggregate
            yield $this->activity->createTransaction($deposit);

            // Step 3: Process with bank (in demo mode, this is instant)
            $bankReference = yield $this->activity->processBankTransfer($deposit);

            // Step 4: Complete the transaction
            yield $this->activity->completeTransaction($deposit, $bankReference);

            // Step 5: Update account balance
            yield $this->activity->updateAccountBalance($deposit);

            Log::info('OpenBanking deposit workflow completed', [
                'reference'      => $deposit->reference,
                'bank_reference' => $bankReference,
            ]);

            return $bankReference;
        } catch (\Exception $e) {
            Log::error('OpenBanking deposit workflow failed', [
                'reference' => $deposit->reference,
                'error'     => $e->getMessage(),
            ]);

            // Attempt to reverse the transaction
            yield $this->activity->reverseTransaction($deposit);

            throw $e;
        }
    }
}

class ProcessOpenBankingDepositActivity
{
    public function validateAccount(string $accountUuid): Account
    {
        $account = Account::where('uuid', $accountUuid)->first();

        if (! $account) {
            throw new \Exception("Account not found: {$accountUuid}");
        }

        // Account validation - in production, check status
        // For now, just verify account exists

        return $account;
    }

    public function createTransaction(OpenBankingDeposit $deposit): void
    {
        TransactionAggregate::retrieve($deposit->reference)
            ->createTransaction(
                transactionUuid: $deposit->reference,
                accountUuid: $deposit->accountUuid,
                amount: $deposit->amount,
                currency: $deposit->currency,
                type: Transaction::TYPE_DEPOSIT,
                description: "OpenBanking deposit from {$deposit->bankName}",
                metadata: $deposit->metadata
            )
            ->persist();
    }

    public function processBankTransfer(OpenBankingDeposit $deposit): string
    {
        // In production, this would call the bank API
        // In demo mode, we simulate instant success
        if (config('demo.mode') || config('demo.sandbox.enabled')) {
            Log::info('Simulating OpenBanking transfer', [
                'reference' => $deposit->reference,
                'bank'      => $deposit->bankName,
            ]);

            // Generate a bank reference
            return 'BANK-' . strtoupper(uniqid());
        }

        // Production implementation would go here
        throw new \Exception('Production OpenBanking integration not implemented');
    }

    public function completeTransaction(OpenBankingDeposit $deposit, string $bankReference): void
    {
        TransactionAggregate::retrieve($deposit->reference)
            ->authorizeTransaction($deposit->reference, $bankReference)
            ->completeTransaction($deposit->reference, $bankReference)
            ->persist();
    }

    public function updateAccountBalance(OpenBankingDeposit $deposit): void
    {
        $account = Account::where('uuid', $deposit->accountUuid)->firstOrFail();

        // The balance is updated automatically via event projectors
        // This is just for logging
        Log::info('Account balance updated', [
            'account_uuid'   => $deposit->accountUuid,
            'deposit_amount' => $deposit->amount,
            'currency'       => $deposit->currency,
        ]);
    }

    public function reverseTransaction(OpenBankingDeposit $deposit): void
    {
        try {
            TransactionAggregate::retrieve($deposit->reference)
                ->reverseTransaction($deposit->reference, 'Workflow failed')
                ->persist();

            Log::info('Transaction reversed', [
                'reference' => $deposit->reference,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to reverse transaction', [
                'reference' => $deposit->reference,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
