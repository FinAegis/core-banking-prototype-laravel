<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Projectors;

use App\Domain\AgentProtocol\Events\PaymentCompleted;
use App\Domain\AgentProtocol\Events\PaymentFailed;
use App\Domain\AgentProtocol\Events\PaymentInitiated;
use App\Domain\AgentProtocol\Events\PaymentRefunded;
use App\Domain\AgentProtocol\Models\AgentTransaction;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

/**
 * Projector to maintain Payment/Transaction read models from events.
 */
class PaymentProjector extends Projector
{
    /**
     * Handle payment initiated event.
     */
    public function onPaymentInitiated(PaymentInitiated $event): void
    {
        AgentTransaction::create([
            'transaction_id' => $event->transactionId,
            'type'           => 'payment',
            'status'         => 'pending',
            'from_agent_id'  => $event->fromAgentId,
            'to_agent_id'    => $event->toAgentId,
            'amount'         => $event->amount,
            'currency'       => $event->currency,
            'metadata'       => array_merge($event->metadata, [
                'description'  => $event->description,
                'initiated_at' => $event->initiatedAt,
            ]),
        ]);
    }

    /**
     * Handle payment completed event.
     */
    public function onPaymentCompleted(PaymentCompleted $event): void
    {
        $transaction = AgentTransaction::where('transaction_id', $event->transactionId)->first();
        if ($transaction) {
            $transaction->status = 'completed';
            $transaction->completed_at = $event->completedAt;
            $transaction->metadata = array_merge($transaction->metadata ?? [], [
                'completion_reference' => $event->reference,
                'processing_time_ms'   => $event->processingTime,
            ]);
            $transaction->save();
        }
    }

    /**
     * Handle payment failed event.
     */
    public function onPaymentFailed(PaymentFailed $event): void
    {
        $transaction = AgentTransaction::where('transaction_id', $event->transactionId)->first();
        if ($transaction) {
            $transaction->status = 'failed';
            $transaction->failed_at = $event->failedAt;
            $transaction->metadata = array_merge($transaction->metadata ?? [], [
                'failure_reason' => $event->reason,
                'failure_code'   => $event->errorCode,
            ]);
            $transaction->save();
        }
    }

    /**
     * Handle payment refunded event.
     */
    public function onPaymentRefunded(PaymentRefunded $event): void
    {
        $transaction = AgentTransaction::where('transaction_id', $event->transactionId)->first();
        if ($transaction) {
            $transaction->status = 'refunded';
            $transaction->refunded_at = $event->refundedAt;
            $transaction->metadata = array_merge($transaction->metadata ?? [], [
                'refund_amount'    => $event->refundAmount,
                'refund_reason'    => $event->reason,
                'refund_reference' => $event->refundReference,
            ]);
            $transaction->save();

            // Create a new refund transaction if it's a partial refund
            if ($event->refundAmount < $transaction->amount) {
                AgentTransaction::create([
                    'transaction_id' => 'refund_' . $event->transactionId,
                    'type'           => 'refund',
                    'status'         => 'completed',
                    'from_agent_id'  => $transaction->to_agent_id,
                    'to_agent_id'    => $transaction->from_agent_id,
                    'amount'         => $event->refundAmount,
                    'currency'       => $transaction->currency,
                    'metadata'       => [
                        'description'             => 'Refund for transaction: ' . $event->transactionId,
                        'original_transaction_id' => $event->transactionId,
                        'refund_reason'           => $event->reason,
                        'initiated_at'            => $event->refundedAt,
                        'completed_at'            => $event->refundedAt,
                    ],
                ]);
            }
        }
    }
}
