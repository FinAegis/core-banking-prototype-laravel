<?php

declare(strict_types=1);

namespace App\Domain\Payment\Activities;

use App\Domain\Account\Models\Account;
use App\Domain\Payment\DataObjects\OpenBankingDeposit;
use App\Domain\Transaction\Aggregates\TransactionAggregate;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

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
