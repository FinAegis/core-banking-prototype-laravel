<?php

namespace App\Domain\Payment\Projectors;

use App\Domain\Payment\Events\StripeDepositProcessed;
use App\Domain\Payment\Events\BankWithdrawalRequested;
use App\Domain\Payment\Events\BankWithdrawalCompleted;
use App\Domain\Transaction\Models\Transaction;
use App\Models\Account;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class PaymentTransactionProjector extends Projector
{
    public function onStripeDepositProcessed(StripeDepositProcessed $event): void
    {
        $account = Account::where('uuid', $event->accountUuid)->first();
        
        if (!$account) {
            return;
        }

        Transaction::create([
            'account_id' => $account->id,
            'type' => 'deposit',
            'amount' => $event->amount,
            'currency' => $event->currency,
            'status' => 'completed',
            'reference' => $event->reference,
            'external_reference' => $event->externalReference,
            'processor' => 'stripe',
            'metadata' => array_merge($event->metadata, [
                'payment_method' => $event->paymentMethod,
                'payment_method_type' => $event->paymentMethodType,
            ]),
            'processed_at' => now(),
        ]);
    }

    public function onBankWithdrawalRequested(BankWithdrawalRequested $event): void
    {
        $account = Account::where('uuid', $event->accountUuid)->first();
        
        if (!$account) {
            return;
        }

        Transaction::create([
            'account_id' => $account->id,
            'type' => 'withdrawal',
            'amount' => $event->amount,
            'currency' => $event->currency,
            'status' => 'pending',
            'reference' => $event->reference,
            'processor' => 'bank_transfer',
            'metadata' => array_merge($event->metadata, [
                'bank_name' => $event->bankName,
                'account_number' => substr($event->accountNumber, -4), // Only store last 4
                'account_holder_name' => $event->accountHolderName,
                'routing_number' => $event->routingNumber,
                'iban' => $event->iban,
                'swift' => $event->swift,
            ]),
        ]);
    }

    public function onBankWithdrawalCompleted(BankWithdrawalCompleted $event): void
    {
        $transaction = Transaction::where('reference', $event->reference)->first();
        
        if (!$transaction) {
            return;
        }

        $transaction->update([
            'status' => $event->status,
            'processed_at' => now(),
            'metadata' => array_merge($transaction->metadata ?? [], [
                'transfer_id' => $event->transferId,
                'failure_reason' => $event->failureReason,
                'completed_at' => now()->toIso8601String(),
            ]),
        ]);
    }
}