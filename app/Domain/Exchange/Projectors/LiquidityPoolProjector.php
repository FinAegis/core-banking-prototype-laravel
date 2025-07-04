<?php

namespace App\Domain\Exchange\Projectors;

use App\Models\Account;
use App\Domain\Exchange\Events\LiquidityAdded;
use App\Domain\Exchange\Events\LiquidityPoolCreated;
use App\Domain\Exchange\Events\LiquidityPoolRebalanced;
use App\Domain\Exchange\Events\LiquidityRemoved;
use App\Domain\Exchange\Events\LiquidityRewardsClaimed;
use App\Domain\Exchange\Events\LiquidityRewardsDistributed;
use App\Domain\Exchange\Events\PoolFeeCollected;
use App\Domain\Exchange\Events\PoolParametersUpdated;
use App\Domain\Exchange\Projections\LiquidityPool;
use App\Domain\Exchange\Projections\LiquidityProvider;
use App\Domain\Exchange\Projections\PoolSwap;
use Brick\Math\BigDecimal;
use Illuminate\Support\Str;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class LiquidityPoolProjector extends Projector
{
    public function onLiquidityPoolCreated(LiquidityPoolCreated $event): void
    {
        // Get system user for pool accounts
        $systemUser = \App\Models\User::firstOrCreate(['email' => 'system@finaegis.com'], [
            'name' => 'System',
            'password' => bcrypt('system'),
            'uuid' => Str::uuid()->toString(),
        ]);
        
        // Create a system account for the pool
        $poolAccount = Account::create([
            'uuid' => Str::uuid()->toString(),
            'name' => "Liquidity Pool {$event->baseCurrency}/{$event->quoteCurrency}",
            'user_uuid' => $systemUser->uuid,
            'balance' => 0,
            'frozen' => false,
        ]);

        LiquidityPool::create([
            'pool_id' => $event->poolId,
            'account_id' => $poolAccount->uuid,
            'base_currency' => $event->baseCurrency,
            'quote_currency' => $event->quoteCurrency,
            'base_reserve' => '0',
            'quote_reserve' => '0',
            'total_shares' => '0',
            'fee_rate' => $event->feeRate,
            'is_active' => true,
            'volume_24h' => '0',
            'fees_collected_24h' => '0',
            'metadata' => $event->metadata,
        ]);
    }

    public function onLiquidityAdded(LiquidityAdded $event): void
    {
        $pool = LiquidityPool::where('pool_id', $event->poolId)->firstOrFail();
        
        $pool->update([
            'base_reserve' => $event->newBaseReserve,
            'quote_reserve' => $event->newQuoteReserve,
            'total_shares' => $event->newTotalShares,
        ]);

        // Update or create provider record
        $provider = LiquidityProvider::firstOrNew([
            'pool_id' => $event->poolId,
            'provider_id' => $event->providerId,
        ]);

        $currentShares = BigDecimal::of($provider->shares ?? '0');
        $newShares = $currentShares->plus($event->sharesMinted);

        $provider->fill([
            'shares' => $newShares->__toString(),
            'initial_base_amount' => BigDecimal::of($provider->initial_base_amount ?? '0')
                ->plus($event->baseAmount)->__toString(),
            'initial_quote_amount' => BigDecimal::of($provider->initial_quote_amount ?? '0')
                ->plus($event->quoteAmount)->__toString(),
            'metadata' => array_merge($provider->metadata ?? [], $event->metadata),
        ]);

        $provider->save();
    }

    public function onLiquidityRemoved(LiquidityRemoved $event): void
    {
        $pool = LiquidityPool::where('pool_id', $event->poolId)->firstOrFail();
        
        $pool->update([
            'base_reserve' => $event->newBaseReserve,
            'quote_reserve' => $event->newQuoteReserve,
            'total_shares' => $event->newTotalShares,
        ]);

        // Update provider record
        $provider = LiquidityProvider::where('pool_id', $event->poolId)
            ->where('provider_id', $event->providerId)
            ->firstOrFail();

        $currentShares = BigDecimal::of($provider->shares);
        $newShares = $currentShares->minus($event->sharesBurned);

        if ($newShares->isZero()) {
            // Remove provider if no shares left
            $provider->delete();
        } else {
            $provider->update([
                'shares' => $newShares->__toString(),
            ]);
        }
    }

    public function onPoolFeeCollected(PoolFeeCollected $event): void
    {
        $pool = LiquidityPool::where('pool_id', $event->poolId)->firstOrFail();
        
        // Update 24h metrics
        $currentFees = BigDecimal::of($pool->fees_collected_24h ?? '0');
        $currentVolume = BigDecimal::of($pool->volume_24h ?? '0');
        
        $pool->update([
            'fees_collected_24h' => $currentFees->plus($event->feeAmount)->__toString(),
            'volume_24h' => $currentVolume->plus($event->swapVolume)->__toString(),
        ]);

        // Record swap
        PoolSwap::create([
            'swap_id' => Str::uuid()->toString(),
            'pool_id' => $event->poolId,
            'account_id' => $event->metadata['account_id'] ?? null,
            'input_currency' => $event->currency,
            'input_amount' => $event->swapVolume,
            'output_currency' => $event->metadata['output_currency'] ?? '',
            'output_amount' => $event->metadata['output_amount'] ?? '0',
            'fee_amount' => $event->feeAmount,
            'price_impact' => $event->metadata['price_impact'] ?? '0',
            'execution_price' => $event->metadata['execution_price'] ?? '0',
            'metadata' => $event->metadata,
        ]);
    }

    public function onLiquidityRewardsDistributed(LiquidityRewardsDistributed $event): void
    {
        $pool = LiquidityPool::where('pool_id', $event->poolId)->firstOrFail();
        $providers = LiquidityProvider::where('pool_id', $event->poolId)->get();
        
        $totalShares = BigDecimal::of($event->totalShares);
        $rewardAmount = BigDecimal::of($event->rewardAmount);

        foreach ($providers as $provider) {
            $providerShares = BigDecimal::of($provider->shares);
            if ($providerShares->isGreaterThan(0)) {
                $shareRatio = $providerShares->dividedBy($totalShares, 18);
                $providerReward = $rewardAmount->multipliedBy($shareRatio);
                
                $pendingRewards = $provider->pending_rewards ?? [];
                $currentReward = BigDecimal::of($pendingRewards[$event->rewardCurrency] ?? '0');
                
                $pendingRewards[$event->rewardCurrency] = $currentReward
                    ->plus($providerReward)
                    ->__toString();
                
                $provider->update([
                    'pending_rewards' => $pendingRewards,
                ]);
            }
        }
    }

    public function onLiquidityRewardsClaimed(LiquidityRewardsClaimed $event): void
    {
        $provider = LiquidityProvider::where('pool_id', $event->poolId)
            ->where('provider_id', $event->providerId)
            ->firstOrFail();

        $totalClaimed = BigDecimal::of($provider->total_rewards_claimed ?? '0');
        
        foreach ($event->rewards as $currency => $amount) {
            $totalClaimed = $totalClaimed->plus($amount);
        }

        $provider->update([
            'pending_rewards' => [],
            'total_rewards_claimed' => $totalClaimed->__toString(),
        ]);
    }

    public function onPoolParametersUpdated(PoolParametersUpdated $event): void
    {
        $pool = LiquidityPool::where('pool_id', $event->poolId)->firstOrFail();
        
        $updates = [];
        
        if (isset($event->changes['fee_rate'])) {
            $updates['fee_rate'] = $event->changes['fee_rate'];
        }
        
        if (isset($event->changes['is_active'])) {
            $updates['is_active'] = $event->changes['is_active'];
        }
        
        if (!empty($updates)) {
            $pool->update($updates);
        }
    }

    public function onLiquidityPoolRebalanced(LiquidityPoolRebalanced $event): void
    {
        $pool = LiquidityPool::where('pool_id', $event->poolId)->firstOrFail();
        
        // Log rebalancing in metadata
        $metadata = $pool->metadata ?? [];
        $metadata['last_rebalance'] = [
            'timestamp' => now()->toIso8601String(),
            'old_ratio' => $event->oldRatio,
            'new_ratio' => $event->newRatio,
            'amount' => $event->rebalanceAmount,
            'currency' => $event->rebalanceCurrency,
        ];
        
        $pool->update([
            'metadata' => $metadata,
        ]);
    }
}