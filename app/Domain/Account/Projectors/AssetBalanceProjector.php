<?php

namespace App\Domain\Account\Projectors;

use App\Domain\Account\Actions\CreditAssetBalance;
use App\Domain\Account\Actions\DebitAssetBalance;
use App\Domain\Account\Events\AssetBalanceAdded;
use App\Domain\Account\Events\AssetBalanceSubtracted;
use App\Domain\Account\Services\Cache\CacheManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class AssetBalanceProjector extends Projector implements ShouldQueue
{
    /**
     * Handle asset balance addition events
     */
    public function onAssetBalanceAdded(AssetBalanceAdded $event): void
    {
        app(CreditAssetBalance::class)($event);
        
        // Invalidate cache after balance update
        if ($account = \App\Models\Account::where('uuid', $event->aggregateRootUuid())->first()) {
            app(CacheManager::class)->onAccountUpdated($account);
        }
    }

    /**
     * Handle asset balance subtraction events
     */
    public function onAssetBalanceSubtracted(AssetBalanceSubtracted $event): void
    {
        app(DebitAssetBalance::class)($event);
        
        // Invalidate cache after balance update
        if ($account = \App\Models\Account::where('uuid', $event->aggregateRootUuid())->first()) {
            app(CacheManager::class)->onAccountUpdated($account);
        }
    }
}