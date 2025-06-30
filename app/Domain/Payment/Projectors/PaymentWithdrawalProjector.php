<?php

namespace App\Domain\Payment\Projectors;

use App\Domain\Payment\Events\WithdrawalInitiated;
use App\Domain\Payment\Events\WithdrawalCompleted;
use App\Domain\Payment\Events\WithdrawalFailed;
use App\Models\PaymentTransaction;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class PaymentWithdrawalProjector extends Projector
{
    public function onWithdrawalInitiated(WithdrawalInitiated $event, string $aggregateUuid): void
    {
        PaymentTransaction::create([
            'aggregate_uuid' => $aggregateUuid,
            'account_uuid' => $event->accountUuid,
            'type' => 'withdrawal',
            'status' => 'pending',
            'amount' => $event->amount,
            'currency' => $event->currency,
            'reference' => $event->reference,
            'bank_account_number' => $event->bankAccountNumber,
            'bank_routing_number' => $event->bankRoutingNumber,
            'bank_account_name' => $event->bankAccountName,
            'metadata' => $event->metadata,
            'initiated_at' => now(),
        ]);
    }

    public function onWithdrawalCompleted(WithdrawalCompleted $event, string $aggregateUuid): void
    {
        PaymentTransaction::where('aggregate_uuid', $aggregateUuid)
            ->update([
                'status' => 'completed',
                'transaction_id' => $event->transactionId,
                'completed_at' => $event->completedAt,
            ]);
    }

    public function onWithdrawalFailed(WithdrawalFailed $event, string $aggregateUuid): void
    {
        PaymentTransaction::where('aggregate_uuid', $aggregateUuid)
            ->update([
                'status' => 'failed',
                'failed_reason' => $event->reason,
                'failed_at' => $event->failedAt,
            ]);
    }
}