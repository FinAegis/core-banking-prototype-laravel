<?php

declare(strict_types=1);

namespace App\Domain\Account\Projectors;

use App\Domain\Account\Models\TransactionProjection;
use App\Domain\Asset\Events\AssetTransactionCreated;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

/**
 * Writes history rows for single-account asset deposits and withdrawals.
 * Transfers and conversions get their rows from AssetTransferProjector, in the
 * same transaction that moves their balances.
 */
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
                    'reference'    => $event->aggregateRootUuid(),
                    'hash'         => $event->hash->getHash(),
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
}
