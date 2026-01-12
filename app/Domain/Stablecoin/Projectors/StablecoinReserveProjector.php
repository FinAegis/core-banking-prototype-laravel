<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Projectors;

use App\Domain\Stablecoin\Events\CustodianAdded;
use App\Domain\Stablecoin\Events\ReserveDeposited;
use App\Domain\Stablecoin\Events\ReservePoolCreated;
use App\Domain\Stablecoin\Events\ReserveRebalanced;
use App\Domain\Stablecoin\Events\ReserveWithdrawn;
use App\Domain\Stablecoin\Models\StablecoinReserve;
use App\Domain\Stablecoin\Models\StablecoinReserveAuditLog;
use Illuminate\Support\Str;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

/**
 * Projects ReservePool aggregate events to StablecoinReserve read model.
 */
class StablecoinReserveProjector extends Projector
{
    /**
     * Handle reserve pool creation.
     */
    public function onReservePoolCreated(ReservePoolCreated $event): void
    {
        // Pool is created but no reserves yet - just store metadata if needed
        // Reserves are created when deposits are made
    }

    /**
     * Handle reserve deposit.
     */
    public function onReserveDeposited(ReserveDeposited $event): void
    {
        // Find or create the reserve record
        $reserve = StablecoinReserve::where('pool_id', $event->poolId)
            ->where('asset_code', $event->asset)
            ->first();

        if (! $reserve) {
            $reserve = StablecoinReserve::create([
                'reserve_id'            => (string) Str::uuid(),
                'pool_id'               => $event->poolId,
                'stablecoin_code'       => $this->getStablecoinCodeForPool($event->poolId),
                'asset_code'            => $event->asset,
                'amount'                => '0',
                'value_usd'             => '0',
                'allocation_percentage' => '0',
                'custodian_id'          => $event->custodianId,
                'status'                => 'active',
            ]);
        }

        /** @var numeric-string $amountBefore */
        $amountBefore = (string) $reserve->amount;
        /** @var numeric-string $depositAmount */
        $depositAmount = $event->amount;
        $newAmount = bcadd($amountBefore, $depositAmount, 18);

        $reserve->amount = $newAmount;
        $reserve->save();

        // Create audit log
        StablecoinReserveAuditLog::create([
            'reserve_id'       => $reserve->reserve_id,
            'pool_id'          => $event->poolId,
            'stablecoin_code'  => $reserve->stablecoin_code,
            'asset_code'       => $event->asset,
            'action'           => 'deposit',
            'amount_change'    => $event->amount,
            'amount_before'    => $amountBefore,
            'amount_after'     => $newAmount,
            'transaction_hash' => $event->transactionHash,
            'custodian_id'     => $event->custodianId,
            'executed_by'      => 'system',
            'reason'           => 'Reserve deposit',
            'executed_at'      => now(),
            'metadata'         => $event->metadata,
        ]);

        // Update allocations for all reserves in this pool
        $this->updateAllocations($event->poolId);
    }

    /**
     * Handle reserve withdrawal.
     */
    public function onReserveWithdrawn(ReserveWithdrawn $event): void
    {
        $reserve = StablecoinReserve::where('pool_id', $event->poolId)
            ->where('asset_code', $event->asset)
            ->first();

        if (! $reserve) {
            return;
        }

        /** @var numeric-string $amountBefore */
        $amountBefore = (string) $reserve->amount;
        /** @var numeric-string $withdrawAmount */
        $withdrawAmount = $event->amount;
        $newAmount = bcsub($amountBefore, $withdrawAmount, 18);

        $reserve->amount = $newAmount;
        $reserve->save();

        // Create audit log
        StablecoinReserveAuditLog::create([
            'reserve_id'      => $reserve->reserve_id,
            'pool_id'         => $event->poolId,
            'stablecoin_code' => $reserve->stablecoin_code,
            'asset_code'      => $event->asset,
            'action'          => 'withdrawal',
            'amount_change'   => '-' . $event->amount,
            'amount_before'   => $amountBefore,
            'amount_after'    => $newAmount,
            'custodian_id'    => $event->custodianId,
            'executed_by'     => 'system',
            'reason'          => $event->reason,
            'executed_at'     => now(),
            'metadata'        => array_merge($event->metadata, [
                'destination_address' => $event->destinationAddress,
            ]),
        ]);

        // Update allocations
        $this->updateAllocations($event->poolId);
    }

    /**
     * Handle reserve rebalancing.
     */
    public function onReserveRebalanced(ReserveRebalanced $event): void
    {
        foreach ($event->swaps as $swap) {
            // Deduct from source asset
            $fromReserve = StablecoinReserve::where('pool_id', $event->poolId)
                ->where('asset_code', $swap['from_asset'])
                ->first();

            if ($fromReserve) {
                /** @var numeric-string $amountBefore */
                $amountBefore = (string) $fromReserve->amount;
                /** @var numeric-string $fromAmount */
                $fromAmount = $swap['from_amount'];
                $newAmount = bcsub($amountBefore, $fromAmount, 18);
                $fromReserve->amount = $newAmount;
                $fromReserve->save();

                StablecoinReserveAuditLog::create([
                    'reserve_id'      => $fromReserve->reserve_id,
                    'pool_id'         => $event->poolId,
                    'stablecoin_code' => $fromReserve->stablecoin_code,
                    'asset_code'      => $swap['from_asset'],
                    'action'          => 'rebalance',
                    'amount_change'   => '-' . $swap['from_amount'],
                    'amount_before'   => $amountBefore,
                    'amount_after'    => $newAmount,
                    'executed_by'     => $event->executedBy,
                    'reason'          => 'Reserve rebalancing - swap out',
                    'executed_at'     => now(),
                    'metadata'        => ['swap' => $swap],
                ]);
            }

            // Add to destination asset
            $toReserve = StablecoinReserve::where('pool_id', $event->poolId)
                ->where('asset_code', $swap['to_asset'])
                ->first();

            if (! $toReserve) {
                $toReserve = StablecoinReserve::create([
                    'reserve_id'            => (string) Str::uuid(),
                    'pool_id'               => $event->poolId,
                    'stablecoin_code'       => $this->getStablecoinCodeForPool($event->poolId),
                    'asset_code'            => $swap['to_asset'],
                    'amount'                => '0',
                    'value_usd'             => '0',
                    'allocation_percentage' => '0',
                    'status'                => 'active',
                ]);
            }

            /** @var numeric-string $toAmountBefore */
            $toAmountBefore = (string) $toReserve->amount;
            /** @var numeric-string $toAmount */
            $toAmount = $swap['to_amount'];
            $newAmount = bcadd($toAmountBefore, $toAmount, 18);
            $toReserve->amount = $newAmount;
            $toReserve->save();

            StablecoinReserveAuditLog::create([
                'reserve_id'      => $toReserve->reserve_id,
                'pool_id'         => $event->poolId,
                'stablecoin_code' => $toReserve->stablecoin_code,
                'asset_code'      => $swap['to_asset'],
                'action'          => 'rebalance',
                'amount_change'   => $swap['to_amount'],
                'amount_before'   => $toAmountBefore,
                'amount_after'    => $newAmount,
                'executed_by'     => $event->executedBy,
                'reason'          => 'Reserve rebalancing - swap in',
                'executed_at'     => now(),
                'metadata'        => ['swap' => $swap],
            ]);
        }

        // Update allocations
        $this->updateAllocations($event->poolId);
    }

    /**
     * Handle custodian addition - update reserve custodian info.
     */
    public function onCustodianAdded(CustodianAdded $event): void
    {
        // Update any reserves that might need custodian info
        StablecoinReserve::where('pool_id', $event->poolId)
            ->whereNull('custodian_id')
            ->update([
                'custodian_id'   => $event->custodianId,
                'custodian_name' => $event->name,
                'custodian_type' => $this->mapCustodianType($event->type),
            ]);
    }

    /**
     * Update allocation percentages for all reserves in a pool.
     */
    private function updateAllocations(string $poolId): void
    {
        $reserves = StablecoinReserve::where('pool_id', $poolId)->get();

        // Calculate total value (simplified - using amount directly, should use USD values)
        $totalValue = $reserves->sum(fn ($r) => (float) $r->amount);

        if ($totalValue <= 0) {
            return;
        }

        foreach ($reserves as $reserve) {
            $allocation = ((float) $reserve->amount / $totalValue) * 100;
            $reserve->allocation_percentage = number_format($allocation, 4, '.', '');
            $reserve->save();
        }
    }

    /**
     * Get stablecoin code for a pool (would need to look up from pool metadata).
     */
    private function getStablecoinCodeForPool(string $poolId): string
    {
        // In a real implementation, this would look up the stablecoin from pool metadata
        // For now, return a default or derive from pool ID
        return 'FUSD';
    }

    /**
     * Map custodian type string to enum value.
     */
    private function mapCustodianType(string $type): string
    {
        return match ($type) {
            'hot'           => 'hot_wallet',
            'cold'          => 'cold_wallet',
            'institutional' => 'institutional',
            'smart_contract', 'contract' => 'smart_contract',
            default => 'hot_wallet',
        };
    }
}
