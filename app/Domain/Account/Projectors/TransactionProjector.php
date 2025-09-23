<?php

declare(strict_types=1);

namespace App\Domain\Account\Projectors;

use App\Domain\Account\Models\TransactionProjection;
use App\Domain\Asset\Events\AssetTransactionCreated;
use App\Domain\Asset\Events\AssetTransferCompleted;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class TransactionProjector extends Projector
{
    /**
     * Handle asset transaction created event.
     */
    public function onAssetTransactionCreated(AssetTransactionCreated $event): void
    {
        try {
            TransactionProjection::create(
                [
                    'uuid'         => Str::uuid(),
                    'account_uuid' => (string) $event->accountUuid,
                    'type'         => $event->isCredit() ? 'deposit' : 'withdrawal',
                    'asset_code'   => $event->assetCode,
                    'amount'       => $event->getAmount(),
                    'description'  => $event->description ?? ($event->isCredit() ? 'Deposit' : 'Withdrawal'),
                    'reference'    => $event->transactionId ?? null,
                    'status'       => 'completed',
                    'metadata'     => [
                        'event_type' => 'AssetTransactionCreated',
                        'event_uuid' => $event->aggregateRootUuid(),
                    ],
                ]
            );

            Log::info(
                'Transaction projection created for AssetTransactionCreated',
                [
                    'account_uuid' => (string) $event->accountUuid,
                    'asset_code'   => $event->assetCode,
                    'amount'       => $event->getAmount(),
                ]
            );
        } catch (Exception $e) {
            Log::error(
                'Error creating transaction projection',
                [
                    'event' => 'AssetTransactionCreated',
                    'error' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Handle asset transfer completed event.
     */
    public function onAssetTransferCompleted(AssetTransferCompleted $event): void
    {
        try {
            // Create debit transaction for sender
            TransactionProjection::create(
                [
                    'uuid'         => Str::uuid(),
                    'account_uuid' => (string) $event->fromAccountUuid,
                    'type'         => 'transfer_out',
                    'asset_code'   => $event->assetCode,
                    'amount'       => $event->amount,
                    'description'  => $event->description ?? 'Transfer to ' . substr((string) $event->toAccountUuid, 0, 8),
                    'reference'    => $event->transferId ?? null,
                    'status'       => 'completed',
                    'metadata'     => [
                        'event_type' => 'AssetTransferCompleted',
                        'event_uuid' => $event->aggregateRootUuid(),
                        'to_account' => (string) $event->toAccountUuid,
                    ],
                ]
            );

            // Create credit transaction for receiver
            TransactionProjection::create(
                [
                    'uuid'         => Str::uuid(),
                    'account_uuid' => (string) $event->toAccountUuid,
                    'type'         => 'transfer_in',
                    'asset_code'   => $event->assetCode,
                    'amount'       => $event->amount,
                    'description'  => $event->description ?? 'Transfer from ' . substr((string) $event->fromAccountUuid, 0, 8),
                    'reference'    => $event->transferId ?? null,
                    'status'       => 'completed',
                    'metadata'     => [
                        'event_type'   => 'AssetTransferCompleted',
                        'event_uuid'   => $event->aggregateRootUuid(),
                        'from_account' => (string) $event->fromAccountUuid,
                    ],
                ]
            );

            Log::info(
                'Transaction projections created for AssetTransferCompleted',
                [
                    'from_account' => (string) $event->fromAccountUuid,
                    'to_account'   => (string) $event->toAccountUuid,
                    'asset_code'   => $event->assetCode,
                    'amount'       => $event->amount,
                ]
            );
        } catch (Exception $e) {
            Log::error(
                'Error creating transaction projections for transfer',
                [
                    'event' => 'AssetTransferCompleted',
                    'error' => $e->getMessage(),
                ]
            );
        }
    }
}
