<?php

declare(strict_types=1);

namespace App\Domain\Account\Projectors;

use App\Domain\Account\Models\TransactionProjection;
use App\Domain\Asset\Events\AssetTransactionCreated;
use App\Domain\Shared\EventSourcing\AppliedStoredEvents;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

/**
 * Writes history rows for single-account asset deposits and withdrawals.
 * Transfers and conversions get their rows from AssetTransferProjector, in the
 * same transaction that moves their balances.
 *
 * Each stored event gets one row. The insert claims the stored event in
 * applied_stored_events in the same transaction, so a replay or a second
 * delivery adds no row.
 */
class TransactionProjector extends Projector
{
    private const PROJECTION = 'asset_transaction_history';

    /**
     * Handle asset transaction created event.
     */
    public function onAssetTransactionCreated(AssetTransactionCreated $event): void
    {
        try {
            $connection = TransactionProjection::query()->getConnection();

            $written = $connection->transaction(function () use ($connection, $event): bool {
                if (! AppliedStoredEvents::claim($connection, self::PROJECTION, $event)) {
                    return false;
                }

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

                return true;
            });

            if (! $written) {
                Log::info(
                    'Transaction projection for AssetTransactionCreated already written, skipped',
                    [
                        'stored_event_id' => $event->storedEventId(),
                        'account_uuid'    => (string) $event->accountUuid,
                    ]
                );

                return;
            }

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
