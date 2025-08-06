<?php

declare(strict_types=1);

namespace App\Domain\Payment\Activities;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\TransactionProjection;
use App\Domain\Payment\DataObjects\OpenBankingDeposit;
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
        TransactionProjection::create([
            'uuid'         => $deposit->reference,
            'account_uuid' => $deposit->accountUuid,
            'amount'       => $deposit->amount,
            'asset_code'   => $deposit->currency,
            'type'         => 'deposit',
            'status'       => 'pending',
            'reference'    => $deposit->reference,
            'description'  => "OpenBanking deposit from {$deposit->bankName}",
            'metadata'     => $deposit->metadata,
            'hash'         => hash('sha3-512', $deposit->reference . $deposit->accountUuid . time()),
        ]);
    }

    public function processBankTransfer(OpenBankingDeposit $deposit): string
    {
        // In production, this would call the bank API
        // In demo environment, we simulate instant success
        if (app()->environment('demo') || config('demo.sandbox.enabled')) {
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
        $transaction = TransactionProjection::where('uuid', $deposit->reference)->firstOrFail();
        $transaction->update([
            'status'             => 'completed',
            'external_reference' => $bankReference,
            'metadata'           => array_merge($transaction->metadata ?? [], [
                'bank_reference' => $bankReference,
                'completed_at'   => now()->toIso8601String(),
            ]),
        ]);
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
            $transaction = TransactionProjection::where('uuid', $deposit->reference)->first();

            if ($transaction && $transaction->status !== 'reversed') {
                $transaction->update([
                    'status'   => 'reversed',
                    'metadata' => array_merge($transaction->metadata ?? [], [
                        'reversed_at'     => now()->toIso8601String(),
                        'reversal_reason' => 'Workflow failed',
                    ]),
                ]);

                Log::info('Transaction reversed', [
                    'reference' => $deposit->reference,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to reverse transaction', [
                'reference' => $deposit->reference,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
