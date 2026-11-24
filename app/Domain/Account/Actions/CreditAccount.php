<?php

namespace App\Domain\Account\Actions;

use App\Domain\Account\Events\AssetBalanceAdded;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Shared\EventSourcing\AppliedStoredEvents;

/**
 * Projects AssetBalanceAdded onto account_balances for AccountProjector, the
 * only projector of this event. The balance row is locked, and the stored
 * event is claimed in the same transaction, so a replay or a second delivery
 * changes nothing.
 */
class CreditAccount extends AccountAction
{
    public const PROJECTION = 'account_asset_balance';

    public function __invoke(AssetBalanceAdded $event): Account
    {
        $account = $this->accountRepository->findByUuid(
            $event->aggregateRootUuid()
        );

        $connection = AccountBalance::query()->getConnection();

        $connection->transaction(function () use ($connection, $account, $event): void {
            if (! AppliedStoredEvents::claim($connection, self::PROJECTION, $event)) {
                return;
            }

            AccountBalance::query()->createOrFirst(
                ['account_uuid' => $account->uuid, 'asset_code' => $event->assetCode],
                ['balance' => 0]
            );

            // Add to balance amount (in smallest unit)
            AccountBalance::query()
                ->where('account_uuid', $account->uuid)
                ->where('asset_code', $event->assetCode)
                ->lockForUpdate()
                ->firstOrFail()
                ->credit($event->amount);
        }, 3);

        return $account->fresh();
    }
}
