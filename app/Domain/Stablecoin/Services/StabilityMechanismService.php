<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Services;

use App\Domain\Asset\Services\ExchangeRateService;
use App\Models\Stablecoin;
use App\Models\StablecoinCollateralPosition;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StabilityMechanismService
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRateService,
        private readonly CollateralService $collateralService,
        private readonly LiquidationService $liquidationService
    ) {}

    /**
     * Execute stability mechanisms for all active stablecoins.
     */
    public function executeStabilityMechanisms(): array
    {
        $results = [];
        $stablecoins = Stablecoin::active()->get();
        
        foreach ($stablecoins as $stablecoin) {
            try {
                $result = $this->executeStabilityMechanismForStablecoin($stablecoin);
                $results[$stablecoin->code] = $result;
            } catch (\Exception $e) {
                Log::error('Stability mechanism failed', [
                    'stablecoin_code' => $stablecoin->code,
                    'error' => $e->getMessage(),
                ]);
                
                $results[$stablecoin->code] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $results;
    }

    /**
     * Execute stability mechanism for a specific stablecoin.
     */
    public function executeStabilityMechanismForStablecoin(Stablecoin $stablecoin): array
    {
        $mechanism = $stablecoin->stability_mechanism;
        
        return match ($mechanism) {
            'collateralized' => $this->executeCollateralizedMechanism($stablecoin),
            'algorithmic' => $this->executeAlgorithmicMechanism($stablecoin),
            'hybrid' => $this->executeHybridMechanism($stablecoin),
            default => throw new \InvalidArgumentException("Unknown stability mechanism: {$mechanism}")
        };
    }

    /**
     * Execute collateralized stability mechanism.
     */
    private function executeCollateralizedMechanism(Stablecoin $stablecoin): array
    {
        $actions = [];
        $metrics = $this->collateralService->getSystemCollateralizationMetrics()[$stablecoin->code] ?? null;
        
        if (!$metrics) {
            return ['success' => false, 'error' => 'No metrics available'];
        }
        
        // 1. Check global collateralization ratio
        $globalRatio = $metrics['global_ratio'];
        $targetRatio = $stablecoin->collateral_ratio;
        $minRatio = $stablecoin->min_collateral_ratio;
        
        // 2. Handle undercollateralization
        if ($globalRatio < $minRatio) {
            $liquidationResult = $this->liquidationService->liquidateEligiblePositions();
            $actions[] = [
                'type' => 'emergency_liquidation',
                'reason' => 'Global collateralization below minimum',
                'details' => $liquidationResult,
            ];
        } elseif ($globalRatio < $targetRatio) {
            // Gradually increase collateral requirements or fees
            $actions[] = $this->adjustCollateralRequirements($stablecoin, 'increase');
        }
        
        // 3. Handle overcollateralization
        if ($globalRatio > $targetRatio * 1.5) {
            // Reduce collateral requirements to encourage more minting
            $actions[] = $this->adjustCollateralRequirements($stablecoin, 'decrease');
        }
        
        // 4. Monitor and liquidate risky positions
        $atRiskPositions = $this->collateralService->getPositionsAtRisk();
        if ($atRiskPositions->isNotEmpty()) {
            $actions[] = [
                'type' => 'risk_monitoring',
                'positions_at_risk' => $atRiskPositions->count(),
                'recommendations' => $this->generateRiskRecommendations($atRiskPositions),
            ];
        }
        
        return [
            'success' => true,
            'mechanism' => 'collateralized',
            'global_ratio' => $globalRatio,
            'target_ratio' => $targetRatio,
            'actions_taken' => $actions,
        ];
    }

    /**
     * Execute algorithmic stability mechanism.
     */
    private function executeAlgorithmicMechanism(Stablecoin $stablecoin): array
    {
        $actions = [];
        $currentPrice = $this->getCurrentPrice($stablecoin);
        $targetPrice = $stablecoin->target_price;
        $priceDeviation = abs($currentPrice - $targetPrice) / $targetPrice;
        
        // Price is too high - encourage burning by reducing fees
        if ($currentPrice > $targetPrice * 1.02) { // 2% threshold
            $actions[] = $this->adjustFees($stablecoin, 'reduce_burn_fee');
            $actions[] = $this->adjustFees($stablecoin, 'increase_mint_fee');
        }
        
        // Price is too low - encourage minting by reducing fees
        if ($currentPrice < $targetPrice * 0.98) { // 2% threshold
            $actions[] = $this->adjustFees($stablecoin, 'reduce_mint_fee');
            $actions[] = $this->adjustFees($stablecoin, 'increase_burn_fee');
        }
        
        // Large deviation - emergency measures
        if ($priceDeviation > 0.05) { // 5% threshold
            $actions[] = [
                'type' => 'emergency_intervention',
                'current_price' => $currentPrice,
                'target_price' => $targetPrice,
                'deviation' => $priceDeviation * 100,
                'action' => 'halt_operations',
            ];
            
            // Temporarily halt minting/burning
            $stablecoin->update([
                'minting_enabled' => false,
                'burning_enabled' => false,
            ]);
        }
        
        return [
            'success' => true,
            'mechanism' => 'algorithmic',
            'current_price' => $currentPrice,
            'target_price' => $targetPrice,
            'price_deviation' => $priceDeviation * 100,
            'actions_taken' => $actions,
        ];
    }

    /**
     * Execute hybrid stability mechanism.
     */
    private function executeHybridMechanism(Stablecoin $stablecoin): array
    {
        // Combine both collateralized and algorithmic mechanisms
        $collateralizedResult = $this->executeCollateralizedMechanism($stablecoin);
        $algorithmicResult = $this->executeAlgorithmicMechanism($stablecoin);
        
        return [
            'success' => $collateralizedResult['success'] && $algorithmicResult['success'],
            'mechanism' => 'hybrid',
            'collateralized_actions' => $collateralizedResult['actions_taken'] ?? [],
            'algorithmic_actions' => $algorithmicResult['actions_taken'] ?? [],
            'global_ratio' => $collateralizedResult['global_ratio'] ?? 0,
            'price_deviation' => $algorithmicResult['price_deviation'] ?? 0,
        ];
    }

    /**
     * Adjust collateral requirements.
     */
    private function adjustCollateralRequirements(Stablecoin $stablecoin, string $direction): array
    {
        $currentRatio = $stablecoin->collateral_ratio;
        $adjustment = 0.05; // 5% adjustment
        
        $newRatio = $direction === 'increase' 
            ? $currentRatio + $adjustment 
            : max($stablecoin->min_collateral_ratio, $currentRatio - $adjustment);
        
        // Don't adjust too frequently
        $cacheKey = "collateral_adjustment:{$stablecoin->code}";
        if (Cache::has($cacheKey)) {
            return [
                'type' => 'collateral_adjustment_skipped',
                'reason' => 'Recent adjustment already made',
            ];
        }
        
        $stablecoin->update(['collateral_ratio' => $newRatio]);
        Cache::put($cacheKey, true, 3600); // 1 hour cooldown
        
        Log::info('Collateral ratio adjusted', [
            'stablecoin_code' => $stablecoin->code,
            'old_ratio' => $currentRatio,
            'new_ratio' => $newRatio,
            'direction' => $direction,
        ]);
        
        return [
            'type' => 'collateral_adjustment',
            'direction' => $direction,
            'old_ratio' => $currentRatio,
            'new_ratio' => $newRatio,
        ];
    }

    /**
     * Adjust minting/burning fees.
     */
    private function adjustFees(Stablecoin $stablecoin, string $feeType): array
    {
        $adjustment = 0.001; // 0.1% adjustment
        $maxFee = 0.01; // 1% maximum fee
        $minFee = 0; // 0% minimum fee
        
        $currentMintFee = $stablecoin->mint_fee;
        $currentBurnFee = $stablecoin->burn_fee;
        
        $updates = [];
        
        switch ($feeType) {
            case 'reduce_mint_fee':
                $newMintFee = max($minFee, $currentMintFee - $adjustment);
                $updates['mint_fee'] = $newMintFee;
                break;
                
            case 'increase_mint_fee':
                $newMintFee = min($maxFee, $currentMintFee + $adjustment);
                $updates['mint_fee'] = $newMintFee;
                break;
                
            case 'reduce_burn_fee':
                $newBurnFee = max($minFee, $currentBurnFee - $adjustment);
                $updates['burn_fee'] = $newBurnFee;
                break;
                
            case 'increase_burn_fee':
                $newBurnFee = min($maxFee, $currentBurnFee + $adjustment);
                $updates['burn_fee'] = $newBurnFee;
                break;
        }
        
        // Don't adjust fees too frequently
        $cacheKey = "fee_adjustment:{$stablecoin->code}:{$feeType}";
        if (Cache::has($cacheKey)) {
            return [
                'type' => 'fee_adjustment_skipped',
                'fee_type' => $feeType,
                'reason' => 'Recent adjustment already made',
            ];
        }
        
        $stablecoin->update($updates);
        Cache::put($cacheKey, true, 1800); // 30 minute cooldown
        
        Log::info('Fee adjusted', [
            'stablecoin_code' => $stablecoin->code,
            'fee_type' => $feeType,
            'old_mint_fee' => $currentMintFee,
            'old_burn_fee' => $currentBurnFee,
            'updates' => $updates,
        ]);
        
        return [
            'type' => 'fee_adjustment',
            'fee_type' => $feeType,
            'updates' => $updates,
        ];
    }

    /**
     * Get current market price for stablecoin.
     */
    private function getCurrentPrice(Stablecoin $stablecoin): float
    {
        // In a real implementation, this would fetch from price oracles
        // For now, simulate price with small random variations around target
        $targetPrice = $stablecoin->target_price;
        $variance = 0.02; // 2% variance
        $randomFactor = (mt_rand() / mt_getrandmax() - 0.5) * 2 * $variance;
        
        return $targetPrice * (1 + $randomFactor);
    }

    /**
     * Generate recommendations for at-risk positions.
     */
    private function generateRiskRecommendations(Collection $atRiskPositions): array
    {
        $recommendations = [];
        
        foreach ($atRiskPositions as $position) {
            $positionRecommendations = $this->collateralService->getPositionRecommendations($position);
            
            if (!empty($positionRecommendations)) {
                $recommendations[] = [
                    'position_uuid' => $position->uuid,
                    'account_uuid' => $position->account_uuid,
                    'current_ratio' => $position->collateral_ratio,
                    'recommendations' => $positionRecommendations,
                ];
            }
        }
        
        return $recommendations;
    }

    /**
     * Check system health and trigger emergency measures if needed.
     */
    public function checkSystemHealth(): array
    {
        $systemHealth = [
            'overall_status' => 'healthy',
            'stablecoin_status' => [],
            'emergency_actions' => [],
        ];
        
        $stablecoins = Stablecoin::active()->get();
        $unhealthyStablecoins = 0;
        
        foreach ($stablecoins as $stablecoin) {
            $metrics = $this->collateralService->getSystemCollateralizationMetrics()[$stablecoin->code] ?? null;
            
            if (!$metrics) {
                continue;
            }
            
            $isHealthy = $metrics['is_healthy'];
            $globalRatio = $metrics['global_ratio'];
            $atRiskCount = $metrics['at_risk_positions'];
            
            $stablecoinStatus = [
                'code' => $stablecoin->code,
                'is_healthy' => $isHealthy,
                'global_ratio' => $globalRatio,
                'at_risk_positions' => $atRiskCount,
                'status' => $isHealthy ? 'healthy' : 'unhealthy',
            ];
            
            // Determine if emergency action is needed
            if (!$isHealthy || $globalRatio < $stablecoin->min_collateral_ratio * 0.9) {
                $stablecoinStatus['status'] = 'critical';
                $unhealthyStablecoins++;
                
                // Trigger emergency liquidation
                $emergencyResult = $this->liquidationService->emergencyLiquidation($stablecoin->code);
                $systemHealth['emergency_actions'][] = [
                    'stablecoin_code' => $stablecoin->code,
                    'action' => 'emergency_liquidation',
                    'result' => $emergencyResult,
                ];
            }
            
            $systemHealth['stablecoin_status'][] = $stablecoinStatus;
        }
        
        // Overall system status
        if ($unhealthyStablecoins > 0) {
            $systemHealth['overall_status'] = $unhealthyStablecoins > 1 ? 'critical' : 'warning';
        }
        
        return $systemHealth;
    }

    /**
     * Rebalance system parameters based on market conditions.
     */
    public function rebalanceSystemParameters(): array
    {
        $rebalanceActions = [];
        $stablecoins = Stablecoin::active()->get();
        
        foreach ($stablecoins as $stablecoin) {
            $metrics = $this->collateralService->getSystemCollateralizationMetrics()[$stablecoin->code] ?? null;
            
            if (!$metrics) {
                continue;
            }
            
            // Analyze collateral distribution
            $distribution = $metrics['collateral_distribution'];
            $diversificationScore = $this->calculateDiversificationScore($distribution);
            
            // If too concentrated in one asset, adjust requirements
            if ($diversificationScore < 0.5) {
                $rebalanceActions[] = [
                    'stablecoin_code' => $stablecoin->code,
                    'action' => 'improve_diversification',
                    'current_score' => $diversificationScore,
                    'recommendation' => 'Encourage collateral diversification through incentives',
                ];
            }
            
            // Adjust parameters based on utilization
            $utilizationRate = $stablecoin->total_supply / ($stablecoin->max_supply ?: PHP_INT_MAX);
            
            if ($utilizationRate > 0.8) {
                $rebalanceActions[] = [
                    'stablecoin_code' => $stablecoin->code,
                    'action' => 'increase_supply_capacity',
                    'current_utilization' => $utilizationRate,
                    'recommendation' => 'Consider increasing max supply or reducing mint requirements',
                ];
            }
        }
        
        return [
            'rebalance_timestamp' => now(),
            'actions_taken' => $rebalanceActions,
        ];
    }

    /**
     * Calculate collateral diversification score (0-1, higher is better).
     */
    private function calculateDiversificationScore(array $distribution): float
    {
        if (empty($distribution)) {
            return 0;
        }
        
        $percentages = collect($distribution)->pluck('percentage');
        $maxPercentage = $percentages->max();
        
        // Simple diversification score: 1 - max_concentration
        return 1 - ($maxPercentage / 100);
    }
}