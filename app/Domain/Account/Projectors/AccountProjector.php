<?php

namespace App\Domain\Account\Projectors;

use App\Domain\Account\Actions\CreateAccount;
use App\Domain\Account\Actions\CreditAccount;
use App\Domain\Account\Actions\DebitAccount;
use App\Domain\Account\Actions\DeleteAccount;
use App\Domain\Account\Actions\FreezeAccount;
use App\Domain\Account\Actions\UnfreezeAccount;
use App\Domain\Account\Events\AccountCreated;
use App\Domain\Account\Events\AccountDeleted;
use App\Domain\Account\Events\AccountFrozen;
use App\Domain\Account\Events\AccountUnfrozen;
use App\Domain\Account\Events\AssetBalanceAdded;
use App\Domain\Account\Events\AssetBalanceSubtracted;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Services\Cache\CacheManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

/**
 * Projects the account lifecycle and the per-asset balance events.
 *
 * This is the only projector of AssetBalanceAdded and AssetBalanceSubtracted.
 * A second projector used to change the same account_balances row, which
 * booked every credit and debit twice.
 */
class AccountProjector extends Projector implements ShouldQueue
{
    public function onAccountCreated(AccountCreated $event): void
    {
        app(CreateAccount::class)($event);
    }

    public function onAssetBalanceAdded(AssetBalanceAdded $event): void
    {
        app(CreditAccount::class)($event);

        // Invalidate cache after balance update
        if ($account = Account::where('uuid', $event->aggregateRootUuid())->first()) {
            app(CacheManager::class)->onAccountUpdated($account);
        }
    }

    public function onAssetBalanceSubtracted(AssetBalanceSubtracted $event): void
    {
        app(DebitAccount::class)($event);

        // Invalidate cache after balance update
        if ($account = Account::where('uuid', $event->aggregateRootUuid())->first()) {
            app(CacheManager::class)->onAccountUpdated($account);
        }
    }

    public function onAccountDeleted(AccountDeleted $event): void
    {
        app(DeleteAccount::class)($event);

        // Clear all caches for deleted account
        app(CacheManager::class)->onAccountDeleted($event->aggregateRootUuid());
    }

    public function onAccountFrozen(AccountFrozen $event): void
    {
        app(FreezeAccount::class)($event);
    }

    public function onAccountUnfrozen(AccountUnfrozen $event): void
    {
        app(UnfreezeAccount::class)($event);
    }
}
