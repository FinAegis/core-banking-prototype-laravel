<?php

declare(strict_types=1);

namespace App\Domain\Asset\Projectors;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Account\Models\TransactionProjection;
use App\Domain\Asset\Events\AssetTransactionCreated;
use App\Domain\Shared\EventSourcing\AppliedStoredEvents;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

/**
 * Applies single-account asset credits and debits to account_balances and
 * writes their history row.
 *
 * The balance change and the history row are written in one transaction, so
 * the history follows the balance: a debit that finds too little money is
 * logged and skipped, as before, and writes no history row. Each stored event
 * is applied once. The same transaction claims the stored event in
 * applied_stored_events, so a replay or a second delivery changes nothing. A
 * refused debit gives its claim up, so a later delivery can still apply it.
 */
class AssetTransactionProjector extends Projector
{
    private const PROJECTION = 'asset_transaction';

    private const APPLIED = 'applied';

    private const ALREADY_APPLIED = 'already_applied';

    private const INSUFFICIENT = 'insufficient';

    /**
     * Handle asset transaction created event.
     */
    public function onAssetTransactionCreated(AssetTransactionCreated $event): void
    {
        $accountUuid = (string) $event->accountUuid;

        try {
            if (! Account::where('uuid', $accountUuid)->exists()) {
                Log::error(
                    'Account not found for asset transaction',
                    [
                        'account_uuid' => $accountUuid,
                        'asset_code'   => $event->assetCode,
                    ]
                );

                return;
            }

            $connection = AccountBalance::query()->getConnection();

            /** @var array{string, int} $result */
            $result = $connection->transaction(function () use ($connection, $event, $accountUuid): array {
                if (! AppliedStoredEvents::claim($connection, self::PROJECTION, $event)) {
                    return [self::ALREADY_APPLIED, 0];
                }

                AccountBalance::query()->createOrFirst(
                    ['account_uuid' => $accountUuid, 'asset_code' => $event->assetCode],
                    ['balance' => 0]
                );
                $accountBalance = AccountBalance::query()
                    ->where('account_uuid', $accountUuid)
                    ->where('asset_code', $event->assetCode)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($event->isCredit()) {
                    $accountBalance->credit($event->getAmount());
                } elseif ($accountBalance->hasSufficientBalance($event->getAmount())) {
                    $accountBalance->debit($event->getAmount());
                } else {
                    AppliedStoredEvents::release($connection, self::PROJECTION, $event);

                    return [self::INSUFFICIENT, $accountBalance->balance];
                }

                $this->writeHistory($event, $accountUuid);

                return [self::APPLIED, $accountBalance->balance];
            }, 3);

            [$outcome, $balance] = $result;

            if ($outcome === self::ALREADY_APPLIED) {
                Log::info(
                    'Asset transaction already applied, skipped',
                    [
                        'stored_event_id' => $event->storedEventId(),
                        'account_uuid'    => $accountUuid,
                        'asset_code'      => $event->assetCode,
                    ]
                );

                return;
            }

            if ($outcome === self::INSUFFICIENT) {
                Log::error(
                    'Insufficient balance for asset transaction',
                    [
                        'account_uuid'     => $accountUuid,
                        'asset_code'       => $event->assetCode,
                        'requested_amount' => $event->getAmount(),
                        'current_balance'  => $balance,
                    ]
                );

                return;
            }

            Log::info(
                'Asset transaction processed successfully',
                [
                    'account_uuid' => $accountUuid,
                    'asset_code'   => $event->assetCode,
                    'type'         => $event->type,
                    'amount'       => $event->getAmount(),
                    'new_balance'  => $balance,
                ]
            );
        } catch (Exception $e) {
            Log::error(
                'Error processing asset transaction',
                [
                    'account_uuid' => $accountUuid,
                    'asset_code'   => $event->assetCode,
                    'type'         => $event->type,
                    'amount'       => $event->getAmount(),
                    'error'        => $e->getMessage(),
                    'trace'        => $e->getTraceAsString(),
                ]
            );

            throw $e;
        }
    }

    private function writeHistory(AssetTransactionCreated $event, string $accountUuid): void
    {
        TransactionProjection::query()->create([
            'uuid'         => (string) Str::uuid(),
            'account_uuid' => $accountUuid,
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
        ]);
    }
}
