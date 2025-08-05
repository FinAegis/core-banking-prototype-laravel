<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Stablecoin\Events\CollateralAdded;
use App\Domain\Stablecoin\Events\PositionLiquidated;
use App\Domain\Stablecoin\Events\StablecoinBurned;
use App\Domain\Stablecoin\Events\StablecoinMinted;
use App\Domain\Stablecoin\Models\Stablecoin;
use App\Domain\Stablecoin\Models\StablecoinPosition;
use App\Domain\Stablecoin\Models\StablecoinTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoStablecoinService
{
    /**
     * Mint stablecoins with demo collateral.
     */
    public function mint(array $data): StablecoinTransaction
    {
        return DB::transaction(function () use ($data) {
            $stablecoin = Stablecoin::where('code', $data['stablecoin_code'])->firstOrFail();
            $position = $this->getOrCreatePosition($data['account_id'], $stablecoin->id);

            // Calculate required collateral
            $collateralRatio = config('demo.demo_data.stablecoin.collateral_ratio', 150) / 100;
            $requiredCollateral = $data['amount'] * $collateralRatio;

            // In demo mode, automatically add collateral
            if (config('demo.features.auto_collateralize', true)) {
                $this->addDemoCollateral($position, $requiredCollateral);
            }

            // Create mint transaction
            $transaction = StablecoinTransaction::create([
                'id'                  => 'demo_mint_' . Str::random(16),
                'stablecoin_id'       => $stablecoin->id,
                'position_id'         => $position->id,
                'account_id'          => $data['account_id'],
                'type'                => 'mint',
                'amount'              => $data['amount'],
                'collateral_amount'   => $requiredCollateral,
                'collateral_currency' => $data['collateral_currency'] ?? 'USD',
                'status'              => 'completed',
                'executed_at'         => now(),
                'metadata'            => array_merge($data['metadata'] ?? [], [
                    'demo_mode'           => true,
                    'auto_collateralized' => true,
                ]),
            ]);

            // Update position
            $position->update([
                'minted_amount'           => $position->minted_amount + $data['amount'],
                'collateral_amount'       => $position->collateral_amount + $requiredCollateral,
                'collateralization_ratio' => $this->calculateRatio($position),
                'last_activity_at'        => now(),
            ]);

            // Update stablecoin supply
            $stablecoin->increment('total_supply', $data['amount']);

            event(new StablecoinMinted($transaction));

            return $transaction;
        });
    }

    /**
     * Burn stablecoins and release collateral.
     */
    public function burn(array $data): StablecoinTransaction
    {
        return DB::transaction(function () use ($data) {
            $stablecoin = Stablecoin::where('code', $data['stablecoin_code'])->firstOrFail();
            $position = StablecoinPosition::where('account_id', $data['account_id'])
                ->where('stablecoin_id', $stablecoin->id)
                ->firstOrFail();

            // Calculate collateral to release
            $burnAmount = min($data['amount'], $position->minted_amount);
            $collateralToRelease = ($burnAmount / $position->minted_amount) * $position->collateral_amount;

            // Create burn transaction
            $transaction = StablecoinTransaction::create([
                'id'                  => 'demo_burn_' . Str::random(16),
                'stablecoin_id'       => $stablecoin->id,
                'position_id'         => $position->id,
                'account_id'          => $data['account_id'],
                'type'                => 'burn',
                'amount'              => $burnAmount,
                'collateral_amount'   => $collateralToRelease,
                'collateral_currency' => $position->collateral_currency,
                'status'              => 'completed',
                'executed_at'         => now(),
                'metadata'            => array_merge($data['metadata'] ?? [], [
                    'demo_mode'       => true,
                    'instant_release' => true,
                ]),
            ]);

            // Update position
            $newMintedAmount = $position->minted_amount - $burnAmount;
            $newCollateralAmount = $position->collateral_amount - $collateralToRelease;

            $position->update([
                'minted_amount'           => $newMintedAmount,
                'collateral_amount'       => $newCollateralAmount,
                'collateralization_ratio' => $newMintedAmount > 0 ? ($newCollateralAmount / $newMintedAmount) * 100 : 0,
                'last_activity_at'        => now(),
                'status'                  => $newMintedAmount <= 0 ? 'closed' : 'active',
            ]);

            // Update stablecoin supply
            $stablecoin->decrement('total_supply', $burnAmount);

            event(new StablecoinBurned($transaction));

            return $transaction;
        });
    }

    /**
     * Add collateral to position.
     */
    public function addCollateral(array $data): StablecoinPosition
    {
        return DB::transaction(function () use ($data) {
            $position = StablecoinPosition::findOrFail($data['position_id']);

            // Add collateral
            $position->increment('collateral_amount', $data['amount']);
            $position->update([
                'collateralization_ratio' => $this->calculateRatio($position),
                'last_activity_at'        => now(),
            ]);

            // Record transaction
            StablecoinTransaction::create([
                'id'                  => 'demo_col_' . Str::random(16),
                'stablecoin_id'       => $position->stablecoin_id,
                'position_id'         => $position->id,
                'account_id'          => $position->account_id,
                'type'                => 'add_collateral',
                'amount'              => 0,
                'collateral_amount'   => $data['amount'],
                'collateral_currency' => $position->collateral_currency,
                'status'              => 'completed',
                'executed_at'         => now(),
                'metadata'            => ['demo_mode' => true],
            ]);

            event(new CollateralAdded($position, $data['amount']));

            return $position->fresh();
        });
    }

    /**
     * Check collateralization status.
     */
    public function checkCollateralization(string $positionId): array
    {
        $position = StablecoinPosition::with('stablecoin')->findOrFail($positionId);
        $minRatio = config('demo.demo_data.stablecoin.liquidation_threshold', 120);
        $targetRatio = config('demo.demo_data.stablecoin.collateral_ratio', 150);

        $currentRatio = $this->calculateRatio($position);
        $status = match (true) {
            $currentRatio < $minRatio    => 'at_risk',
            $currentRatio < $targetRatio => 'warning',
            default                      => 'healthy',
        };

        return [
            'position_id'          => $position->id,
            'current_ratio'        => round($currentRatio, 2),
            'min_ratio'            => $minRatio,
            'target_ratio'         => $targetRatio,
            'status'               => $status,
            'minted_amount'        => $position->minted_amount,
            'collateral_amount'    => $position->collateral_amount,
            'collateral_value_usd' => $this->getCollateralValueUSD($position),
            'required_collateral'  => $position->minted_amount * ($targetRatio / 100),
            'excess_collateral'    => max(0, $position->collateral_amount - ($position->minted_amount * ($targetRatio / 100))),
            'demo'                 => true,
        ];
    }

    /**
     * Simulate liquidation for at-risk positions.
     */
    public function liquidate(string $positionId): array
    {
        return DB::transaction(function () use ($positionId) {
            $position = StablecoinPosition::with('stablecoin')->findOrFail($positionId);
            $liquidationThreshold = config('demo.demo_data.stablecoin.liquidation_threshold', 120);

            // Check if position is actually at risk
            $currentRatio = $this->calculateRatio($position);
            if ($currentRatio >= $liquidationThreshold) {
                throw new \Exception('Position is not eligible for liquidation');
            }

            // Calculate liquidation details
            $liquidationPenalty = 0.1; // 10% penalty
            $collateralToSeize = $position->collateral_amount;
            $debtToCover = $position->minted_amount;
            $penaltyAmount = $collateralToSeize * $liquidationPenalty;
            $liquidatorReward = $penaltyAmount * 0.5; // 50% of penalty goes to liquidator

            // Create liquidation transaction
            $transaction = StablecoinTransaction::create([
                'id'                  => 'demo_liq_' . Str::random(16),
                'stablecoin_id'       => $position->stablecoin_id,
                'position_id'         => $position->id,
                'account_id'          => $position->account_id,
                'type'                => 'liquidation',
                'amount'              => $debtToCover,
                'collateral_amount'   => $collateralToSeize,
                'collateral_currency' => $position->collateral_currency,
                'status'              => 'completed',
                'executed_at'         => now(),
                'metadata'            => [
                    'demo_mode'         => true,
                    'liquidation_ratio' => $currentRatio,
                    'penalty_amount'    => $penaltyAmount,
                    'liquidator_reward' => $liquidatorReward,
                ],
            ]);

            // Close position
            $position->update([
                'minted_amount'           => 0,
                'collateral_amount'       => 0,
                'collateralization_ratio' => 0,
                'status'                  => 'liquidated',
                'liquidated_at'           => now(),
            ]);

            // Update stablecoin supply
            $position->stablecoin->decrement('total_supply', $debtToCover);

            event(new PositionLiquidated($position, $transaction));

            return [
                'transaction_id'    => $transaction->id,
                'position_id'       => $position->id,
                'debt_covered'      => $debtToCover,
                'collateral_seized' => $collateralToSeize,
                'penalty_amount'    => $penaltyAmount,
                'liquidator_reward' => $liquidatorReward,
                'timestamp'         => now()->toIso8601String(),
                'demo'              => true,
            ];
        });
    }

    /**
     * Get positions at risk of liquidation.
     */
    public function getPositionsAtRisk(): array
    {
        $liquidationThreshold = config('demo.demo_data.stablecoin.liquidation_threshold', 120);

        $positions = StablecoinPosition::where('status', 'active')
            ->where('minted_amount', '>', 0)
            ->get()
            ->filter(function ($position) use ($liquidationThreshold) {
                return $this->calculateRatio($position) < $liquidationThreshold;
            });

        return $positions->map(function ($position) {
            return [
                'position_id'                  => $position->id,
                'account_id'                   => $position->account_id,
                'collateralization_ratio'      => $this->calculateRatio($position),
                'minted_amount'                => $position->minted_amount,
                'collateral_amount'            => $position->collateral_amount,
                'estimated_liquidation_reward' => $position->collateral_amount * 0.05, // 5% reward
            ];
        })->toArray();
    }

    /**
     * Simulate stability mechanism execution.
     */
    public function executeStabilityMechanism(string $stablecoinCode): array
    {
        $stablecoin = Stablecoin::where('code', $stablecoinCode)->firstOrFail();

        // Simulate price oracle data
        $currentPrice = $this->getSimulatedPrice($stablecoinCode);
        $targetPrice = 1.00; // USD peg
        $deviation = abs($currentPrice - $targetPrice) / $targetPrice;

        $actions = [];

        // If price too high, increase supply
        if ($currentPrice > 1.02) {
            $actions[] = [
                'action'      => 'decrease_stability_fee',
                'current_fee' => config('demo.demo_data.stablecoin.stability_fee', 2.5),
                'new_fee'     => max(0, config('demo.demo_data.stablecoin.stability_fee', 2.5) - 0.5),
                'reason'      => 'Price above peg, encouraging minting',
            ];
        }
        // If price too low, decrease supply
        elseif ($currentPrice < 0.98) {
            $actions[] = [
                'action'      => 'increase_stability_fee',
                'current_fee' => config('demo.demo_data.stablecoin.stability_fee', 2.5),
                'new_fee'     => config('demo.demo_data.stablecoin.stability_fee', 2.5) + 0.5,
                'reason'      => 'Price below peg, discouraging minting',
            ];
        }

        return [
            'stablecoin'               => $stablecoinCode,
            'current_price'            => $currentPrice,
            'target_price'             => $targetPrice,
            'deviation_percentage'     => round($deviation * 100, 2),
            'total_supply'             => $stablecoin->total_supply,
            'total_collateral'         => $this->getTotalCollateral($stablecoin),
            'system_collateralization' => $this->getSystemCollateralization($stablecoin),
            'recommended_actions'      => $actions,
            'executed'                 => ! empty($actions),
            'timestamp'                => now()->toIso8601String(),
            'demo'                     => true,
        ];
    }

    /**
     * Get or create position for account.
     */
    private function getOrCreatePosition(int $accountId, int $stablecoinId): StablecoinPosition
    {
        return StablecoinPosition::firstOrCreate(
            [
                'account_id'    => $accountId,
                'stablecoin_id' => $stablecoinId,
            ],
            [
                'id'                      => 'demo_pos_' . Str::random(16),
                'minted_amount'           => 0,
                'collateral_amount'       => 0,
                'collateral_currency'     => 'USD',
                'collateralization_ratio' => 0,
                'status'                  => 'active',
                'metadata'                => ['demo_mode' => true],
            ]
        );
    }

    /**
     * Add demo collateral to position.
     */
    private function addDemoCollateral(StablecoinPosition $position, float $amount): void
    {
        $position->update([
            'collateral_amount'   => $position->collateral_amount + $amount,
            'collateral_currency' => 'USD',
            'metadata'            => array_merge($position->metadata ?? [], [
                'demo_collateral_added'  => true,
                'auto_collateral_amount' => $amount,
            ]),
        ]);
    }

    /**
     * Calculate collateralization ratio.
     */
    private function calculateRatio(StablecoinPosition $position): float
    {
        if ($position->minted_amount <= 0) {
            return 0;
        }

        return ($position->collateral_amount / $position->minted_amount) * 100;
    }

    /**
     * Get collateral value in USD.
     */
    private function getCollateralValueUSD(StablecoinPosition $position): float
    {
        // In demo mode, assume 1:1 for simplicity
        // In production, this would use real exchange rates
        return $position->collateral_amount;
    }

    /**
     * Get simulated stablecoin price.
     */
    private function getSimulatedPrice(string $stablecoinCode): float
    {
        // Simulate slight deviation from $1 peg
        $basePrice = 1.00;
        $variation = (rand(-30, 30) / 1000); // ±0.03 variation

        return round($basePrice + $variation, 4);
    }

    /**
     * Get total collateral in system.
     */
    private function getTotalCollateral(Stablecoin $stablecoin): float
    {
        return StablecoinPosition::where('stablecoin_id', $stablecoin->id)
            ->where('status', 'active')
            ->sum('collateral_amount');
    }

    /**
     * Get system-wide collateralization ratio.
     */
    private function getSystemCollateralization(Stablecoin $stablecoin): float
    {
        $totalMinted = StablecoinPosition::where('stablecoin_id', $stablecoin->id)
            ->where('status', 'active')
            ->sum('minted_amount');

        if ($totalMinted <= 0) {
            return 0;
        }

        $totalCollateral = $this->getTotalCollateral($stablecoin);

        return round(($totalCollateral / $totalMinted) * 100, 2);
    }
}
