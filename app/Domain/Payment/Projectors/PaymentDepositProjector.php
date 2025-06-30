<?php

namespace App\Domain\Payment\Projectors;

use App\Domain\Payment\Events\DepositInitiated;
use App\Domain\Payment\Events\DepositCompleted;
use App\Domain\Payment\Events\DepositFailed;
use App\Models\PaymentTransaction;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class PaymentDepositProjector extends Projector
{
    public function onDepositInitiated(DepositInitiated $event, string $aggregateUuid): void
    {
        PaymentTransaction::create([
            'aggregate_uuid' => $aggregateUuid,
            'account_uuid' => $event->accountUuid,
            'type' => 'deposit',
            'status' => 'pending',
            'amount' => $event->amount,
            'currency' => $event->currency,
            'reference' => $event->reference,
            'external_reference' => $event->externalReference,
            'payment_method' => $event->paymentMethod,
            'payment_method_type' => $event->paymentMethodType,
            'metadata' => $event->metadata,
            'initiated_at' => now(),
        ]);
    }

    public function onDepositCompleted(DepositCompleted $event, string $aggregateUuid): void
    {
        PaymentTransaction::where('aggregate_uuid', $aggregateUuid)
            ->update([
                'status' => 'completed',
                'transaction_id' => $event->transactionId,
                'completed_at' => $event->completedAt,
            ]);
    }

    public function onDepositFailed(DepositFailed $event, string $aggregateUuid): void
    {
        PaymentTransaction::where('aggregate_uuid', $aggregateUuid)
            ->update([
                'status' => 'failed',
                'failed_reason' => $event->reason,
                'failed_at' => $event->failedAt,
            ]);
    }
}