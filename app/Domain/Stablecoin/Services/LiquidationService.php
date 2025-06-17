<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Services;

use App\Domain\Asset\Services\ExchangeRateService;
use App\Models\Account;
use App\Models\Stablecoin;
use App\Models\StablecoinCollateralPosition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LiquidationService
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRateService,
        private readonly CollateralService $collateralService
    ) {}

    /**
     * Liquidate a specific position.
     */
    public function liquidatePosition(StablecoinCollateralPosition $position, ?Account $liquidator = null): array
    {
        if (!$position->shouldAutoLiquidate()) {
            throw new \RuntimeException('Position is not eligible for liquidation');
        }

        return DB::transaction(function () use ($position, $liquidator) {
            $stablecoin = $position->stablecoin;
            $liquidationPenalty = $stablecoin->liquidation_penalty;
            
            // Calculate liquidation amounts
            $debtAmount = $position->debt_amount;
            $collateralAmount = $position->collateral_amount;
            $penaltyAmount = (int) ($collateralAmount * $liquidationPenalty);
            $liquidatorReward = (int) ($penaltyAmount * 0.5); // 50% of penalty goes to liquidator
            $protocolFee = $penaltyAmount - $liquidatorReward;
            
            // Amount returned to position owner after penalty
            $returnedCollateral = $collateralAmount - $penaltyAmount;
            
            // If there's a liquidator, they get the reward
            if ($liquidator && $liquidatorReward > 0) {
                $liquidator->addBalance($position->collateral_asset_code, $liquidatorReward);
            }
            
            // Return remaining collateral to position owner (if any)
            if ($returnedCollateral > 0) {
                $position->account->addBalance($position->collateral_asset_code, $returnedCollateral);
            }
            
            // Burn the debt from total supply
            $stablecoin->decrement('total_supply', $debtAmount);
            
            // Update global collateral value
            $collateralValueInPegAsset = $this->collateralService->convertToPegAsset(
                $position->collateral_asset_code,
                $collateralAmount,
                $stablecoin->peg_asset_code
            );
            $stablecoin->decrement('total_collateral_value', $collateralValueInPegAsset);
            
            // Mark position as liquidated
            $position->markAsLiquidated();
            $position->update([
                'collateral_amount' => 0,
                'debt_amount' => 0,
                'collateral_ratio' => 0,
            ]);
            
            $result = [
                'position_uuid' => $position->uuid,
                'liquidated_debt' => $debtAmount,
                'liquidated_collateral' => $collateralAmount,
                'penalty_amount' => $penaltyAmount,
                'liquidator_reward' => $liquidatorReward,
                'protocol_fee' => $protocolFee,
                'returned_to_owner' => $returnedCollateral,
                'liquidator_uuid' => $liquidator?->uuid,
            ];
            
            Log::info('Position liquidated', array_merge($result, [
                'account_uuid' => $position->account_uuid,
                'stablecoin_code' => $stablecoin->code,
                'collateral_asset' => $position->collateral_asset_code,
            ]));
            
            return $result;
        });
    }

    /**
     * Batch liquidate multiple positions.
     */
    public function batchLiquidate(Collection $positions, ?Account $liquidator = null): array
    {
        $results = [];
        $totalReward = 0;
        $totalProtocolFees = 0;
        
        foreach ($positions as $position) {
            try {
                $result = $this->liquidatePosition($position, $liquidator);
                $results[] = $result;
                $totalReward += $result['liquidator_reward'];
                $totalProtocolFees += $result['protocol_fee'];
            } catch (\Exception $e) {
                Log::warning('Failed to liquidate position', [
                    'position_uuid' => $position->uuid,
                    'error' => $e->getMessage(),
                ]);
                
                $results[] = [
                    'position_uuid' => $position->uuid,
                    'error' => $e->getMessage(),
                    'liquidated' => false,
                ];
            }
        }
        
        return [
            'liquidated_count' => collect($results)->where('liquidated', '!==', false)->count(),
            'failed_count' => collect($results)->where('liquidated', false)->count(),
            'total_liquidator_reward' => $totalReward,
            'total_protocol_fees' => $totalProtocolFees,
            'results' => $results,
        ];
    }

    /**
     * Find and liquidate all eligible positions.
     */
    public function liquidateEligiblePositions(?Account $liquidator = null): array
    {
        $eligiblePositions = $this->collateralService->getPositionsForLiquidation();
        
        if ($eligiblePositions->isEmpty()) {
            return [
                'liquidated_count' => 0,
                'failed_count' => 0,
                'total_liquidator_reward' => 0,
                'total_protocol_fees' => 0,
                'results' => [],
            ];
        }
        
        // Sort by liquidation priority (highest first)
        $sortedPositions = $eligiblePositions->sortByDesc(function ($position) {
            return $this->collateralService->calculateLiquidationPriority($position);
        });
        
        return $this->batchLiquidate($sortedPositions, $liquidator);
    }

    /**
     * Calculate potential liquidation reward for a position.
     */
    public function calculateLiquidationReward(StablecoinCollateralPosition $position): array
    {
        if (!$position->shouldAutoLiquidate()) {
            return [
                'eligible' => false,
                'reward' => 0,
                'penalty' => 0,
                'collateral_seized' => 0,
            ];
        }
        
        $liquidationPenalty = $position->stablecoin->liquidation_penalty;
        $penaltyAmount = (int) ($position->collateral_amount * $liquidationPenalty);
        $liquidatorReward = (int) ($penaltyAmount * 0.5);
        
        return [
            'eligible' => true,
            'reward' => $liquidatorReward,
            'penalty' => $penaltyAmount,
            'collateral_seized' => $position->collateral_amount,
            'debt_amount' => $position->debt_amount,
            'collateral_asset' => $position->collateral_asset_code,
            'current_ratio' => $position->collateral_ratio,
            'min_ratio' => $position->stablecoin->min_collateral_ratio,
        ];
    }

    /**
     * Get liquidation opportunities sorted by reward.
     */
    public function getLiquidationOpportunities(int $limit = 50): Collection
    {
        $eligiblePositions = $this->collateralService->getPositionsForLiquidation();
        
        return $eligiblePositions->map(function ($position) {
            $reward = $this->calculateLiquidationReward($position);
            $priority = $this->collateralService->calculateLiquidationPriority($position);
            
            return array_merge($reward, [
                'position_uuid' => $position->uuid,
                'account_uuid' => $position->account_uuid,
                'stablecoin_code' => $position->stablecoin_code,
                'priority_score' => $priority,
                'health_score' => $this->collateralService->calculatePositionHealthScore($position),
            ]);
        })
        ->sortByDesc('priority_score')
        ->take($limit)
        ->values();
    }

    /**
     * Simulate mass liquidation scenario for stress testing.
     */
    public function simulateMassLiquidation(string $stablecoinCode, float $priceDropPercentage): array
    {
        $stablecoin = Stablecoin::findOrFail($stablecoinCode);
        $positions = $stablecoin->activePositions()->get();
        
        $simulation = [];
        $totalLiquidations = 0;
        $totalCollateralSeized = 0;
        $totalDebtLiquidated = 0;
        
        foreach ($positions as $position) {
            // Simulate price drop effect on collateral ratio
            $newRatio = $position->collateral_ratio * (1 - $priceDropPercentage);
            
            if ($newRatio <= $stablecoin->min_collateral_ratio) {
                $liquidationReward = $this->calculateLiquidationReward($position);
                
                $simulation[] = [
                    'position_uuid' => $position->uuid,
                    'current_ratio' => $position->collateral_ratio,
                    'simulated_ratio' => $newRatio,
                    'would_liquidate' => true,
                    'collateral_seized' => $liquidationReward['collateral_seized'],
                    'debt_amount' => $liquidationReward['debt_amount'],
                ];
                
                $totalLiquidations++;
                $totalCollateralSeized += $liquidationReward['collateral_seized'];
                $totalDebtLiquidated += $liquidationReward['debt_amount'];
            } else {
                $simulation[] = [
                    'position_uuid' => $position->uuid,
                    'current_ratio' => $position->collateral_ratio,
                    'simulated_ratio' => $newRatio,
                    'would_liquidate' => false,
                    'collateral_seized' => 0,
                    'debt_amount' => 0,
                ];
            }
        }
        
        $impactPercentage = $positions->count() > 0 
            ? ($totalLiquidations / $positions->count()) * 100 
            : 0;
        
        return [
            'stablecoin_code' => $stablecoinCode,
            'price_drop_percentage' => $priceDropPercentage * 100,
            'total_positions' => $positions->count(),
            'positions_liquidated' => $totalLiquidations,
            'liquidation_impact_percentage' => $impactPercentage,
            'total_collateral_seized' => $totalCollateralSeized,
            'total_debt_liquidated' => $totalDebtLiquidated,
            'detailed_results' => $simulation,
        ];
    }

    /**
     * Emergency liquidation for system stability.
     */
    public function emergencyLiquidation(string $stablecoinCode): array
    {
        $stablecoin = Stablecoin::findOrFail($stablecoinCode);
        
        // Get all positions that are close to liquidation threshold
        $riskThreshold = $stablecoin->min_collateral_ratio + 0.1; // 10% buffer
        $atRiskPositions = $stablecoin->activePositions()
            ->where('collateral_ratio', '<=', $riskThreshold)
            ->get();
        
        if ($atRiskPositions->isEmpty()) {
            return [
                'emergency_triggered' => false,
                'reason' => 'No positions at significant risk',
                'liquidated_count' => 0,
            ];
        }
        
        Log::critical('Emergency liquidation triggered', [
            'stablecoin_code' => $stablecoinCode,
            'at_risk_positions' => $atRiskPositions->count(),
            'risk_threshold' => $riskThreshold,
        ]);
        
        // Force liquidate positions that are actually eligible
        $eligibleForLiquidation = $atRiskPositions->filter(function ($position) {
            return $position->shouldAutoLiquidate();
        });
        
        $result = $this->batchLiquidate($eligibleForLiquidation);
        
        return array_merge($result, [
            'emergency_triggered' => true,
            'at_risk_positions' => $atRiskPositions->count(),
            'risk_threshold' => $riskThreshold,
        ]);
    }
}