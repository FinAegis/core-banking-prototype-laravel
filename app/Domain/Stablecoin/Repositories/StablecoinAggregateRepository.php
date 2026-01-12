<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Repositories;

use App\Domain\Stablecoin\Aggregates\StablecoinAggregate;
use App\Domain\Stablecoin\Contracts\StablecoinAggregateRepositoryInterface;
use Exception;
use Illuminate\Support\Collection;

/**
 * Event-sourced repository implementation for StablecoinAggregate.
 */
class StablecoinAggregateRepository implements StablecoinAggregateRepositoryInterface
{
    /**
     * Find a stablecoin aggregate by ID.
     */
    public function find(string $stablecoinId): ?StablecoinAggregate
    {
        try {
            return StablecoinAggregate::retrieve($stablecoinId);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Find a stablecoin by code.
     */
    public function findByCode(string $code): ?StablecoinAggregate
    {
        $stablecoin = \App\Domain\Stablecoin\Models\Stablecoin::where('code', $code)->first();

        return $stablecoin ? $this->find($stablecoin->uuid) : null;
    }

    /**
     * Find all active stablecoins.
     */
    public function findActive(): Collection
    {
        return \App\Domain\Stablecoin\Models\Stablecoin::where('is_active', true)
            ->get()
            ->map(fn ($model) => $this->find($model->uuid))
            ->filter();
    }

    /**
     * Save a stablecoin aggregate.
     */
    public function save(StablecoinAggregate $stablecoin): void
    {
        $stablecoin->persist();
    }

    /**
     * Delete a stablecoin.
     */
    public function delete(string $stablecoinId): void
    {
        $stablecoin = $this->find($stablecoinId);
        if ($stablecoin) {
            // Close the stablecoin position
            $stablecoin->closePosition('Stablecoin deleted');
            $stablecoin->persist();
        }
    }

    /**
     * Find stablecoins by peg asset.
     */
    public function findByPegAsset(string $pegAssetCode): Collection
    {
        return \App\Domain\Stablecoin\Models\Stablecoin::where('peg_asset_code', $pegAssetCode)
            ->get()
            ->map(fn ($model) => $this->find($model->uuid))
            ->filter();
    }

    /**
     * Get collateralization metrics for a stablecoin.
     */
    public function getCollateralizationMetrics(string $stablecoinId): array
    {
        $stablecoin = \App\Domain\Stablecoin\Models\Stablecoin::find($stablecoinId);

        if (! $stablecoin) {
            return [];
        }

        $positions = \App\Domain\Stablecoin\Models\StablecoinCollateralPosition::where('stablecoin_id', $stablecoinId)
            ->where('status', 'active')
            ->get();

        $totalCollateral = $positions->sum('collateral_amount');
        $totalDebt = $positions->sum('debt_amount');
        $collateralizationRatio = $totalDebt > 0 ? ($totalCollateral / $totalDebt) * 100 : 0;

        return [
            'stablecoin_id'           => $stablecoinId,
            'stablecoin_code'         => $stablecoin->code,
            'total_collateral'        => $totalCollateral,
            'total_debt'              => $totalDebt,
            'collateralization_ratio' => $collateralizationRatio,
            'minimum_ratio'           => $stablecoin->minimum_collateralization_ratio,
            'is_healthy'              => $collateralizationRatio >= $stablecoin->minimum_collateralization_ratio,
            'position_count'          => $positions->count(),
            'at_risk_positions'       => $positions->where('collateralization_ratio', '<', $stablecoin->liquidation_threshold)->count(),
        ];
    }

    /**
     * Find under-collateralized stablecoins.
     */
    public function findUnderCollateralized(float $threshold): Collection
    {
        return \App\Domain\Stablecoin\Models\Stablecoin::where('is_active', true)
            ->get()
            ->filter(function ($stablecoin) use ($threshold) {
                $metrics = $this->getCollateralizationMetrics($stablecoin->uuid);

                return $metrics['collateralization_ratio'] < $threshold;
            })
            ->map(fn ($model) => $this->find($model->uuid))
            ->filter();
    }

    /**
     * Get total supply for a stablecoin.
     */
    public function getTotalSupply(string $stablecoinCode): float
    {
        $stablecoin = \App\Domain\Stablecoin\Models\Stablecoin::where('code', $stablecoinCode)->first();

        if (! $stablecoin) {
            return 0.0;
        }

        return (float) $stablecoin->total_supply;
    }

    /**
     * Get reserve statistics.
     */
    public function getReserveStatistics(string $stablecoinId): array
    {
        $stablecoin = \App\Domain\Stablecoin\Models\Stablecoin::find($stablecoinId);

        if (! $stablecoin) {
            return [];
        }

        // Get reserves from the StablecoinReserve read model
        $reserves = \App\Domain\Stablecoin\Models\StablecoinReserve::where('stablecoin_code', $stablecoin->code)
            ->where('status', 'active')
            ->get();

        $totalReserves = $reserves->sum(fn ($r) => (float) $r->value_usd);
        $totalSupply = (float) $stablecoin->total_supply;
        $reserveRatio = $totalSupply > 0 ? $totalReserves / $totalSupply : 0.0;

        // Build reserve composition with percentages and verification status
        $reserveComposition = $reserves->map(fn ($reserve) => [
            'asset_code'            => $reserve->asset_code,
            'amount'                => $reserve->amount,
            'value_usd'             => $reserve->value_usd,
            'allocation_percentage' => $reserve->allocation_percentage,
            'custodian'             => $reserve->custodian_name,
            'custodian_type'        => $reserve->custodian_type,
            'last_verified_at'      => $reserve->last_verified_at?->toIso8601String(),
            'verification_source'   => $reserve->verification_source,
            'is_recently_verified'  => $reserve->isRecentlyVerified(),
        ]);

        // Get the most recent verification across all reserves
        $lastAuditAt = $reserves->max('last_verified_at');

        // Get primary wallet address (first active reserve with wallet)
        $primaryWallet = $reserves->firstWhere('wallet_address', '!=', null);

        return [
            'stablecoin_id'          => $stablecoinId,
            'stablecoin_code'        => $stablecoin->code,
            'total_reserves'         => $totalReserves,
            'total_supply'           => $totalSupply,
            'reserve_ratio'          => round($reserveRatio, 4),
            'is_fully_backed'        => $reserveRatio >= 1.0,
            'reserve_composition'    => $reserveComposition,
            'reserve_count'          => $reserves->count(),
            'last_audit_at'          => $lastAuditAt?->toIso8601String() ?? $stablecoin->last_audit_at ?? null,
            'reserve_wallet_address' => $primaryWallet !== null ? $primaryWallet->wallet_address : ($stablecoin->reserve_wallet_address ?? null),
        ];
    }
}
