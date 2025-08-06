<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Services;

use App\Domain\Stablecoin\Events\CollateralPositionLiquidated;
use App\Domain\Stablecoin\Events\CollateralPositionUpdated;
use App\Domain\Stablecoin\Events\StablecoinBurned;
use App\Domain\Stablecoin\Events\StablecoinMinted;
use App\Domain\Stablecoin\Models\StablecoinCollateralPosition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoStablecoinService
{
    /**
     * Mint stablecoins with demo collateral.
     */
    public function mint(string $accountId, string $stablecoinId, float $amount, float $collateral): array
    {
        // Validate collateral ratio
        $minRatio = config('demo.demo_data.stablecoin.collateral_ratio', 1.5);
        $collateralPrice = $this->getCollateralPrice('ETH');
        $collateralValue = $collateral * $collateralPrice;
        $requiredCollateral = $amount * $minRatio;

        if ($collateralValue < $requiredCollateral) {
            throw new \Exception('Insufficient collateral. Required: $' . $requiredCollateral);
        }

        $positionId = 'demo_pos_' . Str::random(16);
        $collateralRatio = $collateralValue / $amount;

        return DB::transaction(function () use ($accountId, $stablecoinId, $amount, $collateral, $positionId, $collateralRatio, $collateralPrice, $requiredCollateral) {
            // Create transaction record (as array since StablecoinTransaction model doesn't exist)
            $transaction = [
                'id'            => 'demo_tx_' . Str::random(16),
                'stablecoin_id' => $stablecoinId,
                'account_id'    => $accountId,
                'type'          => 'mint',
                'amount'        => $amount,
                'collateral'    => $collateral,
                'status'        => 'completed',
                'created_at'    => now(),
                'metadata'      => [
                    'demo_mode'           => true,
                    'position_id'         => $positionId,
                    'collateral_ratio'    => $collateralRatio,
                    'collateral_price'    => $collateralPrice,
                    'required_collateral' => $requiredCollateral,
                ],
            ];

            // Create or update collateral position
            $position = StablecoinCollateralPosition::updateOrCreate(
                ['uuid' => $positionId],
                [
                    'account_uuid'          => $accountId,
                    'stablecoin_code'       => $stablecoinId,
                    'collateral_asset_code' => 'ETH',
                    'collateral_amount'     => DB::raw("COALESCE(collateral_amount, 0) + $collateral"),
                    'debt_amount'           => DB::raw("COALESCE(debt_amount, 0) + $amount"),
                    'collateral_ratio'      => $collateralRatio,
                    'status'                => 'active',
                ]
            );

            event(new StablecoinMinted(
                position_uuid: $positionId,
                account_uuid: $accountId,
                stablecoin_code: $stablecoinId,
                amount: (int) $amount,
                metadata: ['collateral' => $collateral]
            ));

            return $transaction;
        });
    }

    /**
     * Burn stablecoins and release collateral.
     */
    public function burn(string $accountId, string $stablecoinId, float $amount): array
    {
        $position = StablecoinCollateralPosition::where('account_uuid', $accountId)
            ->where('stablecoin_code', $stablecoinId)
            ->where('status', 'active')
            ->first();

        if (! $position) {
            throw new \Exception('No active position found for this account and stablecoin');
        }

        if ($position->debt_amount < $amount) {
            throw new \Exception('Cannot burn more than debt amount');
        }

        return DB::transaction(function () use ($accountId, $stablecoinId, $amount, $position) {
            // Create transaction (as array since StablecoinTransaction model doesn't exist)
            $transaction = [
                'id'            => 'demo_tx_' . Str::random(16),
                'stablecoin_id' => $stablecoinId,
                'account_id'    => $accountId,
                'type'          => 'burn',
                'amount'        => $amount,
                'status'        => 'completed',
                'created_at'    => now(),
                'metadata'      => [
                    'demo_mode'   => true,
                    'position_id' => $position->uuid,
                ],
            ];

            // Update position
            $newDebt = max(0, $position->debt_amount - $amount);
            $collateralToReturn = $newDebt > 0
                ? ($amount / $position->debt_amount) * $position->collateral_amount
                : $position->collateral_amount;

            $position->update([
                'debt_amount'       => $newDebt,
                'collateral_amount' => $position->collateral_amount - $collateralToReturn,
                'status'            => $newDebt > 0 ? 'active' : 'closed',
                'collateral_ratio'  => $newDebt > 0 ? ($position->collateral_amount - $collateralToReturn) / $newDebt : 0,
            ]);

            event(new StablecoinBurned(
                position_uuid: $position->uuid,
                account_uuid: $accountId,
                stablecoin_code: $stablecoinId,
                amount: (int) $amount,
                metadata: ['collateral_returned' => $collateralToReturn]
            ));

            return $transaction;
        });
    }

    /**
     * Get position details with health status.
     */
    public function getPosition(string $positionId): array
    {
        $position = StablecoinCollateralPosition::where('uuid', $positionId)->firstOrFail();
        $collateralPrice = $this->getCollateralPrice('ETH');
        $currentCollateralValue = $position->collateral_amount * $collateralPrice;
        $requiredCollateral = $position->debt_amount * config('demo.demo_data.stablecoin.collateral_ratio', 1.5);

        return [
            'position_id'         => $position->uuid,
            'account_id'          => $position->account_uuid,
            'stablecoin_id'       => $position->stablecoin_code,
            'collateral'          => $position->collateral_amount,
            'debt'                => $position->debt_amount,
            'collateral_ratio'    => $position->collateral_ratio,
            'collateral_value'    => $currentCollateralValue,
            'required_collateral' => $requiredCollateral,
            'health'              => $currentCollateralValue >= $requiredCollateral ? 'healthy' : 'at_risk',
            'liquidation_price'   => $position->debt_amount > 0 ? ($position->debt_amount * 1.2) / $position->collateral_amount : 0,
            'status'              => $position->status,
            'demo'                => true,
        ];
    }

    /**
     * Adjust collateral position.
     */
    public function adjustPosition(string $positionId, float $collateral = 0, float $debt = 0): array
    {
        return DB::transaction(function () use ($positionId, $collateral, $debt) {
            $position = StablecoinCollateralPosition::where('uuid', $positionId)->firstOrFail();

            // Note: CollateralAdded event doesn't exist in the codebase
            // We'll use CollateralPositionUpdated instead
            if ($collateral > 0) {
                event(new CollateralPositionUpdated(
                    position_uuid: $position->uuid,
                    collateral_amount: (int) $position->collateral_amount,
                    debt_amount: (int) $position->debt_amount,
                    collateral_ratio: (float) $position->collateral_ratio,
                    status: $position->status,
                    metadata: ['collateral_added' => $collateral]
                ));
            }

            $position->update([
                'collateral_amount' => DB::raw("collateral_amount + $collateral"),
                'debt_amount'       => DB::raw("debt_amount + $debt"),
                'collateral_ratio'  => ($position->collateral_amount + $collateral) / ($position->debt_amount + $debt),
            ]);

            return $this->getPosition($positionId);
        });
    }

    /**
     * Check and liquidate positions at risk.
     */
    public function checkLiquidations(): array
    {
        $liquidationThreshold = config('demo.demo_data.stablecoin.liquidation_threshold', 1.2);
        $liquidated = [];

        $positionsAtRisk = StablecoinCollateralPosition::where('status', 'active')
            ->where('collateral_ratio', '<', $liquidationThreshold)
            ->get();

        foreach ($positionsAtRisk as $position) {
            // Simulate liquidation for demo
            $collateralPrice = $this->getCollateralPrice('ETH');
            $collateralValue = $position->collateral_amount * $collateralPrice;
            $debtValue = $position->debt_amount;
            $currentRatio = $collateralValue / $debtValue;

            if ($currentRatio < $liquidationThreshold) {
                // Calculate liquidation amounts
                $debtToCover = $position->debt_amount * 0.5; // Liquidate 50% of position
                $collateralToSeize = ($debtToCover / $collateralPrice) * 1.1; // 10% penalty
                $penaltyAmount = $collateralToSeize * 0.1;

                $position->update([
                    'debt_amount'       => $position->debt_amount - $debtToCover,
                    'collateral_amount' => max(0, $position->collateral_amount - $collateralToSeize),
                    'status'            => $position->debt_amount - $debtToCover <= 0 ? 'liquidated' : 'active',
                    'collateral_ratio'  => $position->debt_amount - $debtToCover > 0
                        ? ($position->collateral_amount - $collateralToSeize) * $collateralPrice / ($position->debt_amount - $debtToCover)
                        : 0,
                ]);

                event(new CollateralPositionLiquidated(
                    position_uuid: $position->uuid,
                    liquidator_account_uuid: 'demo_liquidator',
                    collateral_seized: (int) $collateralToSeize,
                    debt_repaid: (int) $debtToCover,
                    liquidation_penalty: (int) $penaltyAmount
                ));

                $liquidated[] = [
                    'position_id'       => $position->uuid,
                    'account_id'        => $position->account_uuid,
                    'stablecoin_id'     => $position->stablecoin_code,
                    'debt_covered'      => $debtToCover,
                    'collateral_seized' => $collateralToSeize,
                    'penalty'           => $penaltyAmount,
                ];
            }
        }

        return [
            'checked'             => $positionsAtRisk->count(),
            'liquidated'          => count($liquidated),
            'liquidation_details' => $liquidated,
            'timestamp'           => now()->toIso8601String(),
            'demo'                => true,
        ];
    }

    /**
     * Get system statistics.
     */
    public function getSystemStats(): array
    {
        // Since StablecoinTransaction doesn't exist, we'll use StablecoinCollateralPosition
        $totalMinted = StablecoinCollateralPosition::where('status', 'active')
            ->sum('debt_amount');

        $totalBurned = 0; // Would need to track this separately in production

        $activePositions = StablecoinCollateralPosition::where('status', 'active')->get();
        $totalCollateral = $activePositions->sum('collateral_amount');
        $totalDebt = $activePositions->sum('debt_amount');

        $systemRatio = $totalDebt > 0 ? $totalCollateral / $totalDebt : 0;

        return [
            'total_supply'            => $totalMinted - $totalBurned,
            'total_minted'            => $totalMinted,
            'total_burned'            => $totalBurned,
            'total_collateral'        => $totalCollateral,
            'total_debt'              => $totalDebt,
            'system_collateral_ratio' => round($systemRatio, 2),
            'active_positions'        => $activePositions->count(),
            'at_risk_positions'       => $activePositions->where('collateral_ratio', '<', 1.5)->count(),
            'demo'                    => true,
        ];
    }

    /**
     * Get account positions.
     */
    public function getAccountPositions(string $accountId, ?string $stablecoinId = null): array
    {
        $query = StablecoinCollateralPosition::where('account_uuid', $accountId);

        if ($stablecoinId) {
            $query->where('stablecoin_code', $stablecoinId);
        }

        $positions = $query->get();

        return [
            'account_id'       => $accountId,
            'positions'        => $positions->map(fn ($p) => $this->getPosition($p->uuid)),
            'total_collateral' => $positions->sum('collateral_amount'),
            'total_debt'       => $positions->sum('debt_amount'),
            'demo'             => true,
        ];
    }

    /**
     * Get simulated collateral price.
     */
    private function getCollateralPrice(string $asset): float
    {
        // Simulate ETH price in demo mode
        return match ($asset) {
            'ETH'   => 2000 + rand(-100, 100), // $1900-$2100
            'BTC'   => 40000 + rand(-1000, 1000), // $39k-$41k
            default => 1.0,
        };
    }
}
