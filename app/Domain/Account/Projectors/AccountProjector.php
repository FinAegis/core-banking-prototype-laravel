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
use App\Domain\Account\Services\Cache\CacheManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class AccountProjector extends Projector implements ShouldQueue
{
    /**
     * @param AccountCreated $event
     *
     * @return void
     */
    public function onAccountCreated(AccountCreated $event): void
    {
        app( CreateAccount::class )($event);
    }

    /**
     * @param AssetBalanceAdded $event
     *
     * @return void
     */
    public function onAssetBalanceAdded(AssetBalanceAdded $event): void
    {
        app( CreditAccount::class )($event);
        
        // Invalidate cache after balance update
        if ($account = \App\Models\Account::where('uuid', $event->aggregateRootUuid())->first()) {
            app(CacheManager::class)->onAccountUpdated($account);
        }
    }

    /**
     * @param AssetBalanceSubtracted $event
     *
     * @return void
     */
    public function onAssetBalanceSubtracted(AssetBalanceSubtracted $event): void
    {
        app( DebitAccount::class )($event);
        
        // Invalidate cache after balance update
        if ($account = \App\Models\Account::where('uuid', $event->aggregateRootUuid())->first()) {
            app(CacheManager::class)->onAccountUpdated($account);
        }
    }

    /**
     * @param AccountDeleted $event
     *
     * @return void
     */
    public function onAccountDeleted(AccountDeleted $event): void
    {
        app( DeleteAccount::class )($event);
        
        // Clear all caches for deleted account
        app(CacheManager::class)->onAccountDeleted($event->aggregateRootUuid());
    }

    /**
     * @param AccountFrozen $event
     *
     * @return void
     */
    public function onAccountFrozen(AccountFrozen $event): void
    {
        app( FreezeAccount::class )($event);
    }

    /**
     * @param AccountUnfrozen $event
     *
     * @return void
     */
    public function onAccountUnfrozen(AccountUnfrozen $event): void
    {
        app( UnfreezeAccount::class )($event);
    }
}
