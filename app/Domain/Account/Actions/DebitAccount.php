<?php

namespace App\Domain\Account\Actions;

use App\Domain\Account\Events\AssetBalanceSubtracted;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Shared\EventSourcing\AppliedStoredEvents;
use Exception;

/**
 * Projects AssetBalanceSubtracted onto account_balances for AccountProjector,
 * the only projector of this event. The balance row is locked, and the stored
 * event is claimed in the same transaction, so a replay or a second delivery
 * changes nothing. A debit that finds no balance or too little money throws,
 * as before, and the rollback gives the claim up.
 */
class DebitAccount extends AccountAction
{
    public function __invoke(AssetBalanceSubtracted $event): Account
    {
        $account = $this->accountRepository->findByUuid(
            $event->aggregateRootUuid()
        );

        $connection = AccountBalance::query()->getConnection();

        $connection->transaction(function () use ($connection, $account, $event): void {
            if (! AppliedStoredEvents::claim($connection, CreditAccount::PROJECTION, $event)) {
                return;
            }

            $balance = AccountBalance::query()
                ->where('account_uuid', $account->uuid)
                ->where('asset_code', $event->assetCode)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                throw new Exception("Asset balance not found for {$event->assetCode}");
            }

            // Ensure balance doesn't go negative (should be validated in aggregate)
            if (! $balance->hasSufficientBalance($event->amount)) {
                throw new Exception("Insufficient balance for {$event->assetCode}");
            }

            // Subtract from balance amount (in smallest unit)
            $balance->balance -= $event->amount;
            $balance->save();
        }, 3);

        return $account->fresh();
    }
}
